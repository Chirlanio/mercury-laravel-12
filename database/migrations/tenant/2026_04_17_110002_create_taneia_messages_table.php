<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('taneia_messages')) {
            return;
        }

        Schema::create('taneia_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taneia_conversation_id')
                ->constrained('taneia_conversations')
                ->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->timestamps();

            $table->index(['taneia_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taneia_messages');
    }
};
