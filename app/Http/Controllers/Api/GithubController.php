<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubController extends Controller
{
    private const CACHE_KEY = 'github.active_repos';
    private const BACKUP_CACHE_KEY = 'github.active_repos.backup';
    private const CACHE_TTL = 3600; // 1 hour
    private const RECENT_DAYS = 30;
    private const MAX_COMMIT_LOOKUPS = 20;

    public function repos()
    {
        $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $fresh = $this->fetchFromGithub();

            if ($fresh !== null) {
                Cache::forever(self::BACKUP_CACHE_KEY, $fresh);

                return $fresh;
            }

            // GitHub unreachable / rate-limited — fall back to the last good copy.
            return Cache::get(self::BACKUP_CACHE_KEY, [
                'repos' => [],
                'generated_at' => null,
            ]);
        });

        return response()->json($data);
    }

    private function fetchFromGithub(): ?array
    {
        $username = config('services.github.username');

        try {
            $response = $this->client()
                ->get("https://api.github.com/users/{$username}/repos", [
                    'per_page' => 100,
                    'sort' => 'pushed',
                    'type' => 'owner',
                ]);

            if ($response->failed()) {
                Log::warning('GitHub repos API failed', ['status' => $response->status()]);

                return null;
            }

            $originals = collect($response->json())
                ->reject(fn ($repo) => $repo['fork'] || $repo['archived'] || $repo['private'])
                ->values();

            $since = now()->subDays(self::RECENT_DAYS);

            $repos = $originals->map(function ($repo, $index) use ($since) {
                // Only spend API calls on repos that could have recent commits.
                $recentCommits = 0;
                if ($index < self::MAX_COMMIT_LOOKUPS && $since->lt($repo['pushed_at'])) {
                    $recentCommits = $this->countRecentCommits($repo['full_name'], $since);
                }

                return [
                    'name' => $repo['name'],
                    'full_name' => $repo['full_name'],
                    'description' => $repo['description'],
                    'html_url' => $repo['html_url'],
                    'homepage' => $repo['homepage'] ?: null,
                    'language' => $repo['language'],
                    'topics' => $repo['topics'] ?? [],
                    'stargazers_count' => $repo['stargazers_count'],
                    'forks_count' => $repo['forks_count'],
                    'pushed_at' => $repo['pushed_at'],
                    'created_at' => $repo['created_at'],
                    'recent_commits' => $recentCommits,
                ];
            });

            $sorted = $repos
                ->sortByDesc(fn ($repo) => [$repo['recent_commits'], $repo['pushed_at']])
                ->values()
                ->all();

            return [
                'repos' => $sorted,
                'recent_days' => self::RECENT_DAYS,
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning('GitHub fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function countRecentCommits(string $fullName, $since): int
    {
        try {
            $response = $this->client()
                ->get("https://api.github.com/repos/{$fullName}/commits", [
                    'since' => $since->toIso8601String(),
                    'per_page' => 100,
                ]);

            return $response->successful() ? count($response->json()) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function client()
    {
        $client = Http::timeout(10)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);

        if ($token = config('services.github.token')) {
            $client = $client->withToken($token);
        }

        return $client;
    }
}
