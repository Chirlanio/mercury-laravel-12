<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado da engine de matching entre 2 damaged_products complementares.
 *
 * Convenção: product_a_id < product_b_id (garantido pela engine antes de
 * inserir) — combinada com unique constraint, isso impede match duplicado A↔B
 * em qualquer ordem.
 *
 * `match_score` é decimal 0-100 calculado por DamagedProductMatchingService::
 * computeMatchScore(): pondera idade do registro, nivelamento de prioridade
 * de loja (store_order) e bonificação por marca homóloga.
 *
 * `match_payload` é JSON com snapshot dos atributos no momento do match
 * (referência, tamanhos, pés, marca, lojas) — usado pra trail de auditoria
 * mesmo se os damaged_products mudarem depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_product_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_a_id')->constrained('damaged_products')->cascadeOnDelete();
            $table->foreignId('product_b_id')->constrained('damaged_products')->cascadeOnDelete();

            $table->enum('match_type', ['mismatched_pair', 'damaged_complement']);
            $table->decimal('match_score', 5, 2)->default(0.00); // 0..100
            $table->json('match_payload')->nullable();

            // Direção sugerida da transferência
            $table->foreignId('suggested_origin_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('suggested_destination_store_id')->nullable()->constrained('stores')->nullOnDelete();

            // Status do match (DamageMatchStatus enum)
            $table->string('status', 20)->default('pending');

            // Vinculação com a transferência criada no accept
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();

            // Auditoria do ciclo de vida
            $table->text('reject_reason')->nullable();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['product_a_id', 'product_b_id'], 'uk_match_pair');
            $table->index('status');
            $table->index('match_type');
            $table->index('product_a_id');
            $table->index('product_b_id');
            $table->index('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_product_matches');
    }
};
