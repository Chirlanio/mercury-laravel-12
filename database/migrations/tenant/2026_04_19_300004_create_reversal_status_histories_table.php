<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail de transições de status de estornos. Uma linha por
 * transição, gravada pelo ReversalTransitionService. Usado pela timeline
 * do modal de detalhes (StandardModal.Timeline).
 *
 * `from_status` é null apenas na linha inicial (criação do registro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversal_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reversal_id')->constrained('reversals')->cascadeOnDelete();

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['reversal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversal_status_histories');
    }
};
