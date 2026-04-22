<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #3 (importador).
 *
 * Adiciona `balance_nature` em `chart_of_accounts` (D = Devedora,
 * C = Credora, A = Ambas) e em `cost_centers` (mesma convenção, embora
 * para CCs seja sempre "A" hoje).
 *
 * Contexto: coluna pedida no prompt para espelhar o campo "Natureza
 * Saldo" do export CIGAM. Não existe no schema do prompt #1/#2 (o
 * model ChartOfAccount tem `nature` via AccountingNature enum, mas
 * `nature` representa algo diferente: é uma escolha do projeto —
 * débito/crédito semântico; `balance_nature` é o campo bruto do ERP).
 *
 * Usaremos `balance_nature` só para auditar o que veio do ERP e dar
 * suporte quando o ERP começar a diferenciar (hoje vem tudo "A").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->char('balance_nature', 1)
                ->nullable()
                ->after('nature')
                ->comment('D=Devedora, C=Credora, A=Ambas — valor bruto do ERP (CIGAM Natureza Saldo)');
        });

        Schema::table('cost_centers', function (Blueprint $table) {
            $table->char('balance_nature', 1)
                ->nullable()
                ->after('external_source')
                ->comment('Preservado do ERP (grupo 8 do plano CIGAM)');
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn('balance_nature');
        });

        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropColumn('balance_nature');
        });
    }
};
