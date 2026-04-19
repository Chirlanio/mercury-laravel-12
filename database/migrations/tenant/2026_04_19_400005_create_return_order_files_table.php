<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos múltiplos de um return_order (foto do produto avariado, print
 * da conversa com o cliente, comprovante de postagem, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_order_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_order_id')->constrained('return_orders')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('return_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_order_files');
    }
};
