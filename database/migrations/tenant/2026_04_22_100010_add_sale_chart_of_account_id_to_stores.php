<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Conta analítica de receita por loja (resposta #17 do usuário — opção b).
 * `SaleToDreProjector` (prompt #8) lê esta coluna para decidir em qual
 * conta do plano gravar a receita de venda ao projetar para `dre_actuals`.
 * Se null, cai num fallback global configurado em settings.
 *
 * Validação do scope (só contas analíticas do grupo 3 Receitas) fica no
 * FormRequest do StoreController (quando a UI de configuração for feita).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_chart_of_account_id')
                ->nullable()
                ->after('status_id');

            $table->foreign('sale_chart_of_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->index('sale_chart_of_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['sale_chart_of_account_id']);
            $table->dropIndex(['sale_chart_of_account_id']);
            $table->dropColumn('sale_chart_of_account_id');
        });
    }
};
