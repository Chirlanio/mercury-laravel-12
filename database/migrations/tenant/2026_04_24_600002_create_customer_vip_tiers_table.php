<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classificação VIP anual (Black/Gold) de cada cliente.
 *
 * Fluxo híbrido (user-confirmado):
 *  1. Command `customers:vip-suggest --year=X` roda sobre movements e grava
 *     `suggested_tier` + `total_revenue` com source=auto. `final_tier` fica
 *     inicialmente igual ao sugerido (ou null se não bateu threshold).
 *  2. Marketing abre a aba VIPs, faz curadoria manual (promove, rebaixa,
 *     remove). Isso altera `final_tier`, preenche `curated_at` e
 *     `curated_by_user_id`, e muda source para `manual`.
 *
 * Unique (customer_id, year) — um cliente só tem uma classificação por ano.
 * Histórico preservado por anos: registros de anos anteriores ficam intocados.
 *
 * NENHUMA coluna aqui é tocada pelo CustomerSyncService — tabela totalmente
 * isolada do sync CIGAM. Só FK cascade on delete de customer é intencional:
 * se o cliente for purgado no CIGAM e removido do customers, a classificação
 * perde sentido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_vip_tiers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');

            // Sugestão automática (null se não bateu nenhum threshold)
            $table->enum('suggested_tier', ['black', 'gold'])->nullable();

            // Decisão final da curadoria (null = removido da lista VIP do ano)
            $table->enum('final_tier', ['black', 'gold'])->nullable();

            // Indicadores que justificaram a sugestão (snapshot do ano)
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->unsignedInteger('total_orders')->default(0);

            $table->timestamp('suggested_at')->nullable();
            $table->timestamp('curated_at')->nullable();
            $table->foreignId('curated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('source', ['auto', 'manual'])->default('auto');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['customer_id', 'year']);
            $table->index(['year', 'final_tier']);
            $table->index(['year', 'suggested_tier']);
            $table->index('curated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vip_tiers');
    }
};
