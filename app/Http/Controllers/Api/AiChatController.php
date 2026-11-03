<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SiteAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 網站 AI 助理（V.AI）。
 *
 * POST /api/ai/chat   — SSE 逐字串流。事件格式：
 *   data: {"t":"文字片段"}
 *   data: {"lead":"saved"}        AI 已把訪客的合作需求轉交給 Vito
 *   data: {"error":"訊息"}
 *   data: [DONE]
 * POST /api/ai/vision — 鏡頭畫面 → 一段客觀描述（語音模式用）
 */
class AiChatController extends Controller
{
    /** 一次最多帶給模型的歷史訊息數（含本次提問） */
    private const HISTORY_LIMIT = 12;

    /** 圖片 data URL 上限（前端會壓到 ~100KB，這裡留餘裕） */
    private const IMAGE_MAX = 1_200_000;

    /** AI 回覆中標記「需求已蒐集完成」的隱藏區塊 */
    private const LEAD_OPEN = '<<LEAD>>';
    private const LEAD_CLOSE = '<<END>>';

    public function chat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'messages' => 'required|array|min:1|max:40',
            'messages.*.role' => 'required|string|in:user,assistant',
            'messages.*.content' => 'required|string|max:2000',
            'image' => 'nullable|string|max:'.self::IMAGE_MAX,
        ]);

        $history = array_slice($validated['messages'], -self::HISTORY_LIMIT);

        // 最後一則必須是訪客的提問，避免被拿來做 assistant prefill
        if (end($history)['role'] !== 'user') {
            abort(422, 'Last message must be from user.');
        }

        $image = $validated['image'] ?? null;
        if ($image !== null && ! SiteAi::isImageDataUrl($image)) {
            abort(422, 'Invalid image.');
        }

        $turns = array_map(fn ($m) => ['role' => $m['role'], 'content' => trim($m['content'])], $history);
        if ($image) {
            // 只有本次提問帶圖（舊圖不重送，省 token 也省頻寬）
            $last = array_key_last($turns);
            $turns[$last]['content'] = SiteAi::userContentWithImage($turns[$last]['content'], $image);
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => SiteAi::systemPrompt()."\n\n".$this->leadRules()]],
            $turns,
        );

        $ip = $request->ip();
        $transcript = $validated['messages'];

        return response()->stream(function () use ($messages, $ip, $transcript) {
            // 關閉所有 output buffer，讓每個 token 立即送出
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $emit = function (array|string $payload): void {
                $data = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
                echo "data: {$data}\n\n";
                flush();
            };

            // 先送一個 SSE 註解，讓瀏覽器 / 代理立刻建立串流（也避免部分代理等第一個 byte 才開始轉送）
            echo ': stream-open'."\n\n";
            flush();

            $pending = '';     // 尚未送出的尾段（模型名稱過濾 + 偵測 LEAD 標記用）
            $reply = '';       // 本次完整回覆（不含 LEAD 區塊）
            $leadRaw = null;   // 擷取到的 LEAD 區塊

            $flush = function (bool $final) use (&$pending, &$reply, &$leadRaw, $emit): void {
                if ($leadRaw !== null) {
                    // 已進入 LEAD 區塊：之後的內容全部收進 leadRaw，不顯示給訪客
                    $leadRaw .= $pending;
                    $pending = '';

                    return;
                }

                $pos = strpos($pending, self::LEAD_OPEN);
                if ($pos !== false) {
                    $visible = substr($pending, 0, $pos);
                    $leadRaw = substr($pending, $pos + strlen(self::LEAD_OPEN));
                    $pending = '';
                } elseif ($final) {
                    $visible = $pending;
                    $pending = '';
                } else {
                    // 保留尾端幾個字：模型名稱或 <<LEAD>> 可能被切在兩個 token 之間
                    $keep = max(SiteAi::STREAM_HOLDBACK, strlen(self::LEAD_OPEN));
                    if (strlen($pending) <= $keep) {
                        return;
                    }
                    $cut = strlen($pending) - $keep;
                    // 不要把 UTF-8 多位元組字元切半
                    while ($cut > 0 && (ord($pending[$cut]) & 0xC0) === 0x80) {
                        $cut--;
                    }
                    $visible = substr($pending, 0, $cut);
                    $pending = substr($pending, $cut);
                }

                $visible = SiteAi::scrub($visible);
                if ($visible !== '') {
                    $reply .= $visible;
                    $emit(['t' => $visible]);
                }
            };

            try {
                $response = Http::withOptions(['stream' => true])
                    ->timeout(config('services.ai.timeout'))
                    ->connectTimeout(5)
                    ->acceptJson()
                    ->post(SiteAi::endpoint(), SiteAi::payload($messages, ['stream' => true]));

                if (! $response->successful()) {
                    Log::warning('AI upstream error', ['status' => $response->status()]);
                    $emit(['error' => 'AI 核心暫時無回應，請稍後再試。']);
                    $emit('[DONE]');

                    return;
                }

                $body = $response->toPsrResponse()->getBody();
                $buffer = '';

                while (! $body->eof()) {
                    if (connection_aborted()) {
                        $body->close();

                        return;
                    }

                    // 小塊讀取：上游每個 token 一個事件，讀太大會把多個 token 綁在一起送
                    $buffer .= $body->read(256);

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = trim(substr($buffer, 0, $pos));
                        $buffer = substr($buffer, $pos + 1);

                        if (! str_starts_with($line, 'data:')) {
                            continue;
                        }

                        $data = trim(substr($line, 5));
                        if ($data === '[DONE]') {
                            break 2;
                        }

                        $text = json_decode($data, true)['choices'][0]['delta']['content'] ?? null;
                        if (is_string($text) && $text !== '') {
                            $pending .= $text;
                            $flush(false);
                        }
                    }
                }

                $flush(true);

                if ($leadRaw !== null && $this->saveLead($leadRaw, $transcript, $reply, $ip)) {
                    $emit(['lead' => 'saved']);
                }
            } catch (\Throwable $e) {
                Log::warning('AI stream failed', ['error' => $e->getMessage()]);
                $emit(['error' => 'AI 核心連線中斷，請稍後再試。']);
            }

            $emit('[DONE]');
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no', // 讓 nginx / WAF 不要緩衝 SSE
            'Connection' => 'keep-alive',
        ]);
    }

    /** 鏡頭畫面 → 客觀描述（語音模式會把它塞進語音 AI 的人設，讓它「看得到」） */
    public function vision(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|string|max:'.self::IMAGE_MAX,
        ]);

        if (! SiteAi::isImageDataUrl($validated['image'])) {
            abort(422, 'Invalid image.');
        }

        $text = SiteAi::describeScene($validated['image']);

        return $text === null
            ? response()->json(['error' => 'vision unavailable'], 503)
            : response()->json(['description' => $text]);
    }

    /** 合作需求蒐集規則（只在文字對話使用） */
    private function leadRules(): string
    {
        $open = self::LEAD_OPEN;
        $close = self::LEAD_CLOSE;

        return <<<RULES
## 合作 / 委託需求蒐集
當訪客表示想跟 Vito 合作、委託專案、邀請演講或授課、詢問報價、提供工作機會，或想請 Vito 聯絡他時：
1. 先熱情回應，說明你可以先幫忙整理需求，再轉交給 Vito 本人。
2. 用自然對話一次問一到兩項，蒐集：稱呼、Email（必填）、需求內容（必填：想做什麼、目的）、期望時程、預算範圍、公司或單位（可選）。訪客不想提供的可選項目就跳過。
3. 不要對報價、檔期、是否接案做任何承諾，這些由 Vito 本人決定。
4. 資料齊全後，條列摘要給訪客確認：「以上資訊正確嗎？確認後我會轉交給 Vito。」
5. 只有在訪客明確確認後，才在該則回覆的「最後面」輸出以下區塊（訪客看不到），之後不要再輸出任何文字：
{$open}{"name":"稱呼","email":"Email","company":"公司或單位或空字串","type":"合作類型","need":"需求內容","timeline":"時程或空字串","budget":"預算或空字串"}{$close}
   區塊前的文字要告訴訪客：「已經幫你轉交給 Vito，他會親自透過 Email 跟你聯繫。」
6. 每段對話最多轉交一次；Email 格式不正確時請訪客重新提供，不要輸出區塊。
RULES;
    }

    private function saveLead(string $raw, array $transcript, string $reply, ?string $ip): bool
    {
        $lead = json_decode(trim(explode(self::LEAD_CLOSE, $raw)[0]), true);

        if (! is_array($lead) || trim((string) ($lead['need'] ?? '')) === '') {
            Log::info('AI lead block rejected', ['raw' => mb_substr($raw, 0, 500)]);

            return false;
        }

        $conversation = collect($transcript)
            ->push(['role' => 'assistant', 'content' => $reply])
            ->map(fn ($m) => ($m['role'] === 'user' ? '訪客' : 'V.AI').'：'.mb_substr(trim($m['content']), 0, 1500))
            ->implode("\n\n");

        return SiteAi::storeLead($lead, $conversation, $ip, '文字對話');
    }

    /**
     * 語音對話的合作需求：訪客在畫面上輸入 Email，需求內容由 AI 從語音字幕整理。
     * POST /api/ai/voice-lead {email, name?, transcript: [{role: user|ai, text}]}
     */
    public function voiceLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:100',
            'transcript' => 'nullable|array|max:80',
            'transcript.*.role' => 'required|string|in:user,ai',
            'transcript.*.text' => 'required|string|max:1500',
        ]);

        $conversation = collect($validated['transcript'] ?? [])
            ->map(fn ($m) => ($m['role'] === 'user' ? '訪客' : 'V.AI').'：'.trim($m['text']))
            ->implode("\n");

        $lead = $conversation !== '' ? SiteAi::extractLead($conversation) : [];
        $lead['email'] = $validated['email'];
        if (! empty($validated['name'])) {
            $lead['name'] = $validated['name'];
        }

        $ok = SiteAi::storeLead($lead, $conversation ?: '（語音對話，無字幕紀錄）', $request->ip(), '語音對話');

        return $ok
            ? response()->json(['status' => 'saved', 'summary' => array_filter([
                'type' => $lead['type'] ?? '',
                'need' => $lead['need'] ?? '',
                'timeline' => $lead['timeline'] ?? '',
                'budget' => $lead['budget'] ?? '',
            ])])
            : response()->json(['error' => '目前無法轉交，請稍後再試，或直接寄信到 service@vito1317.com。'], 429);
    }
}
