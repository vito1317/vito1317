<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SiteRag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 給全雙工語音伺服器用的「即時查資料」：
 * 語音伺服器每一輪先把訪客的話轉成文字送來，這裡從 Vito 的論文 / 研究內容檢索相關段落，
 * 語音伺服器把它附在這一輪的人設後面，語音模型仍然直接聽訪客的聲音、直接用自己的聲音回答。
 *
 * 只給本機語音伺服器呼叫：必須帶 X-Voice-Relay-Secret。為了不拖慢通話，只用 BM25（不呼叫 LLM）。
 */
class AiVoiceKnowledgeController extends Controller
{
    private const MAX_CHARS = 1000;

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.ai.voice_relay_secret');
        abort_if($secret === '' || ! hash_equals($secret, (string) $request->header('X-Voice-Relay-Secret')), 403);

        $v = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'previous' => ['nullable', 'string', 'max:2000'],
        ]);

        // 這一句優先；指代不清（「那它怎麼做到的？」）時再參考上一句
        $hits = SiteRag::search($v['query'], 3, 2.0);
        if (! $hits && ! empty($v['previous'])) {
            $hits = SiteRag::search($v['previous'].' '.$v['query'], 3, 2.0);
        }
        if (! $hits) {
            return response()->json(['text' => '']);
        }

        $text = '';
        foreach ($hits as $h) {
            $piece = '（出處：'.$h['source'].'）'.preg_replace('/\s+/u', ' ', mb_substr($h['text'], 0, 420));
            if (mb_strlen($text) + mb_strlen($piece) > self::MAX_CHARS) {
                break;
            }
            $text .= $piece."\n";
        }

        // 語音模型看到簡體原文時容易跟著講簡體，結尾再提醒一次
        return response()->json(['text' => trim($text)."\n（以上是參考資料。請用台灣正體中文（繁體字）口語回答，講兩到四句重點就好。）"]);
    }
}
