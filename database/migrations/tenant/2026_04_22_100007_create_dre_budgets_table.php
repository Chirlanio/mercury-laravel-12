<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Orçado normalizado (1 linha por mês), distinto de `budget_items` (pivot 12
 * colunas). Alimentado por `BudgetToDreProjector` quando um budget_upload é
 * ativado, ou por import manual do DRE, ou pelo command `dre:import-action-plan`
 * do prompt #10.5.
 *
 * Convenção: `entry_date` é sempre o dia 1 do mês (2026-01-01, 2026-02-01, ...).
 * `amount` segue a mesma convenção de sinal de dre_actuals.
 *
 * Sem soft delete. Mudança de versão = linhas novas (com `budget_version`
 * diferente), não UPDATE. Manter histórico explicito por versão é de-facto
 * imutabilidade por design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dre_budgets', function (Blueprint $table) {
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

            $table->string('budget_version', 30);

            $table->unsignedBigInteger('budget_upload_id')->nullable();
            $table->foreign('budget_upload_id')
                ->references('id')
                ->on('budget_uploads')
                ->nullOnDelete();

            $table->string('notes', 500)->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            // Índices ----------------------------------------------------

            // Query principal da matriz
            $table->index(
                ['entry_date', 'store_id', 'chart_of_account_id', 'cost_center_id', 'budget_version'],
                'dre_budgets_matrix_idx'
            );

            // Drill por conta e versão
            $table->index(
                ['chart_of_account_id', 'entry_date', 'budget_version'],
                'dre_budgets_account_version_idx'
            );

            // Reprojeção quando BudgetUpload muda
            $table->index('budget_upload_id');

            $table->index('budget_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dre_budgets');
    }
};
