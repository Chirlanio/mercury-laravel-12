<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Rename da tabela `accounting_classes` para `chart_of_accounts`. As 4 FKs
 * que apontavam para `accounting_classes` (budget_items, management_classes,
 * cost_centers.default_accounting_class_id, order_payments) continuam
 * funcionando porque o MySQL atualiza a constraint automaticamente ao
 * renomear o alvo. As colunas FK mantêm o nome `accounting_class_id` por
 * ora; renomeá-las para `chart_of_account_id` fica para um prompt futuro
 * de limpeza, depois que todo o código DRE estiver migrado.
 *
 * O model `AccountingClass` é reescrito (no mesmo prompt) como alias
 * `extends ChartOfAccount` para retro-compat com ~30 arquivos que ainda
 * referenciam o nome antigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_classes') && ! Schema::hasTable('chart_of_accounts')) {
            Schema::rename('accounting_classes', 'chart_of_accounts');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chart_of_accounts') && ! Schema::hasTable('accounting_classes')) {
            Schema::rename('chart_of_accounts', 'accounting_classes');
        }
    }
};
