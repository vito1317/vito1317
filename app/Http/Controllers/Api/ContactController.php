<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use App\Mail\NewContactMessage;
use App\Mail\ContactFormReceipt;
use App\Services\SiteAi;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
            'botfight_token' => 'required|string',
        ], [
            'botfight_token.required' => '請先完成人機驗證。',
        ]);

        $this->verifyBotfight($validatedData['botfight_token'], $request->ip());
        unset($validatedData['botfight_token']);

        $contact = Contact::create($validatedData);

        // AI 撰寫初步回覆需要數秒～數十秒：先回應訪客，寄信與 AI 生成在回應送出後才執行
        dispatch(function () use ($contact) {
            $aiReply = SiteAi::draftEmailReply($contact);

            try {
                Mail::to(config('services.contact.recipient'))->send(new NewContactMessage($contact, $aiReply));
            } catch (\Throwable $e) {
                Log::error('Contact owner mail failed', ['contact' => $contact->id, 'error' => $e->getMessage()]);
            }

            try {
                Mail::to($contact->email)->send(new ContactFormReceipt($contact, $aiReply));
            } catch (\Throwable $e) {
                Log::error('Contact receipt mail failed', ['contact' => $contact->id, 'error' => $e->getMessage()]);
            }
        })->afterResponse();

        return response()->json([
            'message' => '訊息已成功寄出，我會盡快回覆您！確認信（含 AI 助理的初步回覆）將在一分鐘內寄到您的信箱。(若未收到，請檢查垃圾郵件夾)',
        ], 201);
    }

    /**
     * 以 Security One BotFight siteverify 驗證人機 token（單次有效）。
     * 驗證不通過或服務異常時擋下請求（fail-closed）。
     */
    private function verifyBotfight(string $token, ?string $ip): void
    {
        try {
            $response = Http::timeout(10)
                ->post(config('services.botfight.verify_url'), [
                    'secret' => config('services.botfight.secret'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            $result = $response->json();

            if ($response->successful() && ($result['success'] ?? false) === true) {
                return;
            }

            Log::info('BotFight verification rejected', [
                'error_codes' => $result['error-codes'] ?? null,
                'ip' => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BotFight siteverify unreachable', ['error' => $e->getMessage()]);
        }

        throw ValidationException::withMessages([
            'botfight_token' => '人機驗證未通過，請重新完成驗證後再送出。',
        ]);
    }
}
