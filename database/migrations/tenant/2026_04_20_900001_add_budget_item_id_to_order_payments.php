<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona FK reversa de OrderPayment para BudgetItem — fundação do
 * dashboard de consumo previsto × realizado (Fase 3 do Budgets).
 *
 * FK nullable com onDelete=null: OP não deve ser deletada quando um
 * item de orçamento for removido. A FK perde o vínculo, mas o registro
 * financeiro continua rastreável independentemente.
 *
 * Index composto (budget_item_id, date) para queries de consumo por
 * período ficarem rápidas — dashboard filtra por mês do orçamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_item_id')->nullable()->after('cost_center_id');

            $table->foreign('budget_item_id')
                ->references('id')
                ->on('budget_items')
                ->nullOnDelete();

            $table->index('budget_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropForeign(['budget_item_id']);
            $table->dropIndex(['budget_item_id']);
            $table->dropColumn('budget_item_id');
        });
    }
};
