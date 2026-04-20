<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara OrderPayment para integração com Budgets e futuro módulo DRE.
 *
 * Adiciona:
 *   - accounting_class_id (nullable): conta contábil da despesa. Junto com
 *     cost_center_id, identifica univocamente o budget_item correspondente
 *     no ano. Também é o elo para classificação no DRE (receita/CMV/despesa
 *     por grupo contábil).
 *
 *   - competence_date (nullable): regime de competência. Diferente de
 *     date_payment (fluxo de caixa). DRE usa competência — despesa de março
 *     paga em abril é contabilizada em março. No cadastro, default sugerido
 *     pela UI é o mesmo mês de date_payment, mas editável para ajustes de
 *     fechamento contábil.
 *
 * Tudo nullable neste commit para não quebrar OPs existentes. O backfill
 * + NOT NULL virá em commit posterior (C3 do roadmap de integração),
 * depois de os usuários adotarem o novo fluxo.
 *
 * Índices:
 *   - accounting_class_id: lookups de consumo por classe contábil
 *   - (cost_center_id, accounting_class_id): match O(1) para resolver o
 *     budget_item no controller
 *   - competence_date: relatórios de DRE por período
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('accounting_class_id')
                ->nullable()
                ->after('cost_center_id');

            $table->foreign('accounting_class_id')
                ->references('id')
                ->on('accounting_classes')
                ->nullOnDelete();

            $table->date('competence_date')->nullable()->after('date_payment');

            $table->index('accounting_class_id');
            $table->index(['cost_center_id', 'accounting_class_id'], 'op_cc_ac_idx');
            $table->index('competence_date');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropForeign(['accounting_class_id']);
            $table->dropIndex(['accounting_class_id']);
            $table->dropIndex('op_cc_ac_idx');
            $table->dropIndex(['competence_date']);
            $table->dropColumn(['accounting_class_id', 'competence_date']);
        });
    }
};
