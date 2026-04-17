<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos múltiplos de um estorno (NF digitalizada, print do cliente,
 * comprovante de chargeback da adquirente, etc.). Substitui o campo
 * `arquivo` único da v1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversal_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reversal_id')->constrained('reversals')->cascadeOnDelete();
            $table->string('file_name');       // nome original
            $table->string('file_path');       // path no storage
            $table->string('file_type', 50)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('reversal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversal_files');
    }
};
