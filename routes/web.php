<?php

use App\Http\Controllers\PortfolioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortfolioController::class, 'index']);
Route::get('/login', [\App\Http\Controllers\PasskeyLoginController::class, 'showLoginForm']);
Route::post('/login/passkey', [\App\Http\Controllers\PasskeyLoginController::class, 'handlePasskeyLogin']);
Route::view('/register-passkey', 'register-passkey');
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*$');

Route::passkeys();
