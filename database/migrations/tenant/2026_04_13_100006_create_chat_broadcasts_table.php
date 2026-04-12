<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_broadcasts')) {
            return;
        }

        Schema::create('chat_broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('message_text');
            $table->enum('message_type', ['text', 'image', 'video', 'file'])->default('text');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->enum('priority', ['normal', 'important', 'urgent'])->default('normal');
            $table->enum('target_type', ['all', 'access_level', 'store', 'custom'])->default('all');
            $table->json('target_ids')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_broadcasts');
    }
};
