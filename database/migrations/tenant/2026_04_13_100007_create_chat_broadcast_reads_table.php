<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_broadcast_reads')) {
            return;
        }

        Schema::create('chat_broadcast_reads', function (Blueprint $table) {
            $table->id();
            $table->uuid('broadcast_id');
            $table->foreign('broadcast_id')->references('id')->on('chat_broadcasts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['broadcast_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_broadcast_reads');
    }
};
