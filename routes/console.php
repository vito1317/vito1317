<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 信箱來信由 V.AI 自動回覆（略過通知與宣傳郵件）
Schedule::command('inbox:ai-reply')->everyMinute()->withoutOverlapping(10)->runInBackground();
