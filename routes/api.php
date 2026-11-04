<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Algorithm;
use App\Models\Project;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\GithubController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AiHistoryController;

Route::get('/projects', function () {
    return Project::orderBy('display_order', 'asc')->get();
});

Route::get('/github/repos', [GithubController::class, 'repos']);
Route::get('/github/contributions', [GithubController::class, 'contributions']);

Route::get('/algorithms', function () {
    return Algorithm::orderBy('display_order', 'asc')->get();
});


Route::post('/contact', [ContactController::class, 'store']);

// AI 助理（串流 SSE）：每 IP 每分鐘 8 次、每小時 60 次
Route::post('/ai/chat', [AiChatController::class, 'chat'])->middleware('throttle:ai-chat');
// 鏡頭畫面描述（語音模式每 ~6 秒一張）：每 IP 每分鐘 15 次、每小時 240 次
Route::post('/ai/suggest', [AiChatController::class, 'suggest'])->middleware('throttle:ai-suggest');
Route::post('/ai/voice-lead', [AiChatController::class, 'voiceLead'])->middleware('throttle:ai-chat');
Route::post('/ai/voice-knowledge', \App\Http\Controllers\Api\AiVoiceKnowledgeController::class)->middleware('throttle:120,1');
Route::post('/ai/vision', [AiChatController::class, 'vision'])->middleware('throttle:ai-vision');

// V.AI 聊天紀錄與跨對話記憶（以瀏覽器本機的 visitor_id 限定只能存取自己的資料）
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/ai/history', [AiHistoryController::class, 'index']);
    Route::get('/ai/history/{id}', [AiHistoryController::class, 'show'])->whereUuid('id');
    Route::delete('/ai/history/{id}', [AiHistoryController::class, 'destroy'])->whereUuid('id');
    Route::post('/ai/history/voice', [AiHistoryController::class, 'storeVoice']);
    Route::get('/ai/memory', [AiHistoryController::class, 'memory']);
    Route::delete('/ai/memory', [AiHistoryController::class, 'forget']);
});
