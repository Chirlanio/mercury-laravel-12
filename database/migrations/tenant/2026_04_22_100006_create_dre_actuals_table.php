<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Espelho canônico unificado de lançamentos realizados. 3 fontes:
 *   - ORDER_PAYMENT (projetor automático quando OrderPayment.status → done)
 *   - SALE (projetor automático quando Sale é criada)
 *   - MANUAL_IMPORT / CIGAM_BALANCE (importador de balancete)
 *
 * `source_type` + `source_id` polimórficos permitem drill-through até a
 * origem (ex: da célula da matriz para o OrderPayment original).
 *
 * `amount` é sinalizado (receita positiva, despesa negativa). Importador
 * e projetores aplicam a conversão conforme account_group (§2.5.1 do plano).
 *
 * `reported_in_closed_period` (bool) é marcado pelos projetores quando
 * entry_date ≤ closed_up_to_date do último fechamento. Usado pela tela
 * de reabertura para gerar relatório consolidado de diferenças snapshot
 * × live (resposta #23 — default).
 *
 * Sem soft delete. Fonte canônica é imutável. Cancelar OP → remove linha
 * via observer; estorno contábil → insere linha nova (não edita a antiga).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dre_actuals', function (Blueprint $table) {
            $table->id();

            $table->date('entry_date');

            $table->unsignedBigInteger('chart_of_account_id');
            $table->foreign('chart_of_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->restrictOnDelete();

            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id')
                ->references('id')
                ->on('cost_centers')
                ->nullOnDelete();

            $table->unsignedBigInteger('store_id')->nullable();
            $table->foreign('store_id')
                ->references('id')
                ->on('stores')
                ->nullOnDelete();

            $table->decimal('amount', 15, 2);

            // source: 'ORDER_PAYMENT' / 'SALE' / 'MANUAL_IMPORT' / 'CIGAM_BALANCE'
            $table->string('source', 20);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('document', 60)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('external_id', 100)->nullable();

            $table->boolean('reported_in_closed_period')->default(false);

            $table->timestamp('imported_at')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            // Índices ----------------------------------------------------

            // Filtro mais comum na matriz DRE
            $table->index(
                ['entry_date', 'store_id', 'chart_of_account_id', 'cost_center_id'],
                'dre_actuals_matrix_idx'
            );

            // Drill por conta
            $table->index(['chart_of_account_id', 'entry_date'], 'dre_actuals_account_date_idx');

            // Reverse lookup (qual dre_actual veio deste OrderPayment)
            $table->unique(['source_type', 'source_id'], 'dre_actuals_source_unique');

            // Dedup de imports manuais
            $table->index(['source', 'external_id'], 'dre_actuals_source_external_idx');

            // Tela de reabertura lista pendentes
            $table->index('reported_in_closed_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dre_actuals');
    }
};
