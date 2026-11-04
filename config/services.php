<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'username' => env('GITHUB_USERNAME', 'vito1317'),
    ],

    'botfight' => [
        'secret' => env('SEC1_BOTFIGHT_SECRET'),
        'verify_url' => env('SEC1_BOTFIGHT_VERIFY_URL', 'https://waf.cybersecureone.com/bf/v1/siteverify'),
    ],

    // 信箱來信自動回覆（php artisan inbox:ai-reply，每分鐘排程）；IMAP 預設沿用寄信的 SMTP 帳號
    'inbox_ai' => [
        'enabled' => env('INBOX_AI_ENABLED', true),
        'host' => env('INBOX_IMAP_HOST', env('MAIL_HOST', 'mail.spacemail.com')),
        'port' => (int) env('INBOX_IMAP_PORT', 993),
        'username' => env('INBOX_IMAP_USERNAME', env('MAIL_USERNAME')),
        'password' => env('INBOX_IMAP_PASSWORD', env('MAIL_PASSWORD')),
        'sent_folder' => env('INBOX_SENT_FOLDER', 'Sent'),
    ],

    'contact' => [
        'recipient' => env('CONTACT_FORM_RECIPIENT', 'service@vito1317.com'),
    ],

    // 本機 llama.cpp（OpenAI 相容 API），供網站 AI 助理 V.AI 使用（模型名稱只留在後端）
    'ai' => [
        'base_url' => env('AI_BASE_URL', 'http://127.0.0.1:10003/v1'),
        'model' => env('AI_MODEL', 'gemma-4-26B-A4B-it-qat-q4_0.gguf'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 700),
        'timeout' => (int) env('AI_TIMEOUT', 90),
        // 本機語音伺服器呼叫 /api/ai/voice-knowledge 用的共用密鑰
        'voice_relay_secret' => env('VOICE_RELAY_SECRET', ''),
    ],

];
