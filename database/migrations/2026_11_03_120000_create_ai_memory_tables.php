<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V.AI 的聊天紀錄與跨對話記憶。
 * 訪客不用登入：以瀏覽器本機產生的隨機 visitor_id 辨識。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('visitor_id', 64)->index();
            $table->string('title', 120)->default('');
            $table->string('channel', 10)->default('text'); // text | voice
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->index();
            $table->string('role', 10); // user | assistant
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('ai_visitor_memories', function (Blueprint $table) {
            $table->string('visitor_id', 64)->primary();
            $table->text('memory');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visitor_memories');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
