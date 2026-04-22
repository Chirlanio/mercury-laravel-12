<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #3 (importador).
 *
 * Torna `chart_of_accounts.nature` (AccountingNature enum debit/credit) e
 * `chart_of_accounts.dre_group` (DreGroup enum) nullable.
 *
 * Motivo: esses campos são conceitos semânticos do plano gerencial
 * decididos pelo CFO/operador da aplicação — não vêm do export do ERP.
 * Quando o importador cria uma conta nova a partir do XLSX, ele só
 * conhece `balance_nature` (coluna "Natureza Saldo" do ERP) e
 * `account_group` (1..5 do V_Grupo). Obrigar `nature` e `dre_group`
 * no insert forçaria heurística frágil ("grupo 3 = CREDIT" etc) sem
 * permitir revisão humana — é melhor deixar null e deixar a UI do
 * prompt #5 (CRUD de mapping) cuidar.
 *
 * Registros existentes (seed do prompt #1) já têm valores preenchidos —
 * não são afetados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            // SQLite (testes) só permite alterar nullable via doctrine/dbal
            // ou re-criação da tabela. Laravel 12 expõe `change()` nativo
            // que usa recreate-pattern em SQLite transparentemente.
            $table->string('nature', 10)->nullable()->change();
            $table->string('dre_group', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Não reverter — voltar pra NOT NULL quebraria linhas importadas
        // que ainda não foram classificadas pelo CFO. Down vazio deliberado.
    }
};
