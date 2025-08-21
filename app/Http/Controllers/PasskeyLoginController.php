<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Spatie\Passkey\Http\Requests\LoginRequest;

class PasskeyLoginController extends Controller
{
    public function showLoginForm(Request $request)
    {
        $redirect = $request->input('redirect', '/');
        return view('passkey-login', compact('redirect'));
    }

    public function handlePasskeyLogin(LoginRequest $request)
    {
        // 用 spatie/laravel-passkeys 驗證登入
        $user = $request->authenticate();

        // 產生 Sanctum token
        $token = $user->createToken('passkey-login')->plainTextToken;

        // 產生一次性 code，將 token 存入 cache
        $code = Str::random(40);
        Cache::put('passkey_code_' . $code, $token, now()->addMinutes(5));

        // 導回主站
        $redirect = $request->input('redirect', '/');
        return redirect()->away($redirect . '?code=' . $code);
    }
}
