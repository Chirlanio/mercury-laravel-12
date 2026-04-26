<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trail de transições de status do damaged_product (Auditável + visível em UI
 * via StandardModal.Timeline).
 *
 * `triggered_by_match_id` distingue cascatas (resolução automática do parceiro
 * do match) de ações diretas — útil pra auditoria e pra que a UI possa renderizar
 * "Resolvido em cascata pelo match #123" em vez de só "Resolvido".
 *
 * Nome do índice forçado pra evitar nome auto >64 chars (gotcha conhecido do MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_product_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damaged_product_id')->constrained('damaged_products')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->foreignId('triggered_by_match_id')->nullable()->constrained('damaged_product_matches')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['damaged_product_id', 'created_at'], 'idx_dp_history_dp_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_product_status_histories');
    }
};
