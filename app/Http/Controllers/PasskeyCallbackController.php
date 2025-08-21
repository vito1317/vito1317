<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class PasskeyCallbackController extends Controller
{
    public function exchangeCode(Request $request)
    {
        $code = $request->input('code');
        $token = Cache::pull('passkey_code_' . $code);

        if (!$token) {
            return response()->json(['error' => 'Invalid or expired code'], 400);
        }

        return response()->json([
            'token' => $token,
        ]);
    }
}
