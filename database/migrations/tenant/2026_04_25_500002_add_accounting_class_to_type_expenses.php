<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona vínculo opcional de `type_expenses` a uma classe contábil
 * (chart_of_accounts) para preparar a integração futura com DRE.
 *
 * Quando preenchido, cada item de prestação herda implicitamente a
 * `accounting_class_id` do tipo escolhido (Alimentação, Transporte etc),
 * o que permite alimentar a matriz DRE com despesas reais de viagem
 * sem precisar perguntar a classe a cada lançamento.
 *
 * O hook de projeção em si será adicionado em iteração futura — esta
 * migration apenas garante a existência do campo no schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_expenses', function (Blueprint $table) {
            // FK opcional para chart_of_accounts (mesma tabela usada por
            // AccountingClass). Sem foreign constraint formal: o schema
            // do plano de contas pode ser hidratado depois do tenant.
            $table->unsignedBigInteger('accounting_class_id')->nullable()->after('color');
            $table->index('accounting_class_id', 'idx_type_expenses_accounting_class');
        });
    }

    public function down(): void
    {
        Schema::table('type_expenses', function (Blueprint $table) {
            $table->dropIndex('idx_type_expenses_accounting_class');
            $table->dropColumn('accounting_class_id');
        });
    }
};
