<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AiMemory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V.AI 聊天紀錄：列出 / 讀取 / 刪除對話、存語音通話紀錄、「忘記我」。
 * 一律以 visitor_id（瀏覽器本機的隨機代號）限定只能存取自己的資料。
 */
class AiHistoryController extends Controller
{
    private function visitor(Request $request): string
    {
        $id = (string) ($request->input('visitor_id') ?? $request->query('visitor_id', ''));
        abort_unless(AiMemory::validVisitor($id), 422, 'Invalid visitor.');

        return $id;
    }

    /** GET /api/ai/history?visitor_id= → 對話列表 + 記憶摘要 */
    public function index(Request $request): JsonResponse
    {
        $visitor = $this->visitor($request);

        return response()->json([
            'conversations' => AiConversation::where('visitor_id', $visitor)
                ->orderByDesc('updated_at')->take(50)
                ->get(['id', 'title', 'channel', 'updated_at']),
            'memory' => AiMemory::memoryFor($visitor),
        ]);
    }

    /** GET /api/ai/history/{id}?visitor_id= → 單一對話的訊息 */
    public function show(Request $request, string $id): JsonResponse
    {
        $conv = AiConversation::where('id', $id)->where('visitor_id', $this->visitor($request))->firstOrFail();

        return response()->json([
            'id' => $conv->id,
            'title' => $conv->title,
            'channel' => $conv->channel,
            'messages' => $conv->messages()->get(['role', 'content', 'created_at']),
        ]);
    }

    /** DELETE /api/ai/history/{id} */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $conv = AiConversation::where('id', $id)->where('visitor_id', $this->visitor($request))->firstOrFail();
        $conv->messages()->delete();
        $conv->delete();

        return response()->json(['status' => 'deleted']);
    }

    /** POST /api/ai/history/voice → 存一通語音通話的逐字紀錄，並更新記憶 */
    public function storeVoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => 'required|string|max:64',
            'transcript' => 'required|array|min:1|max:120',
            'transcript.*.role' => 'required|string|in:user,ai',
            'transcript.*.text' => 'required|string|max:1500',
        ]);
        $visitor = $this->visitor($request);

        $first = collect($validated['transcript'])->firstWhere('role', 'user')['text'] ?? '語音通話';
        $conv = AiMemory::conversation($visitor, null, '📞 '.$first, 'voice');
        foreach ($validated['transcript'] as $line) {
            AiMemory::addMessage($conv, $line['role'] === 'user' ? 'user' : 'assistant', $line['text']);
        }

        // 記憶更新需要幾秒，回應送出後才做
        dispatch(fn () => AiMemory::update($visitor, AiMemory::transcriptText($conv, 30)))->afterResponse();

        return response()->json(['status' => 'saved', 'id' => $conv->id]);
    }

    /** GET /api/ai/memory?visitor_id= → 給語音模式放進人設用 */
    public function memory(Request $request): JsonResponse
    {
        return response()->json(['memory' => AiMemory::memoryFor($this->visitor($request))]);
    }

    /** DELETE /api/ai/memory → 「忘記我」：刪除所有對話與記憶 */
    public function forget(Request $request): JsonResponse
    {
        AiMemory::forget($this->visitor($request));

        return response()->json(['status' => 'forgotten']);
    }
}
