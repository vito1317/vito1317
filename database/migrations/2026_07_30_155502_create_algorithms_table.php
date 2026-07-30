<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('algorithms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('tagline');
            $table->string('category');
            $table->string('status_label');
            // positive / negative / production / research — 前端據此決定徽章配色
            $table->string('status_type')->default('research');
            $table->text('problem');
            $table->text('approach');
            $table->text('formula')->nullable();
            $table->json('highlights')->nullable();
            $table->json('metrics')->nullable();
            $table->json('links')->nullable();
            $table->string('tags')->nullable();
            $table->integer('display_order')->default(99);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('algorithms');
    }
};
