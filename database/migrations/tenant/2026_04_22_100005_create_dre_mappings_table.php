<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * De-para que amarra (conta contábil analítica + centro de custo opcional)
 * a uma linha da DRE gerencial. Precedência no resolver (prompt #6):
 * mapping específico com CC bate antes do coringa (CC null).
 *
 * Vigência temporal via `effective_from` (obrigatório) + `effective_to`
 * (null = vigente). Reclassificações retroativas em períodos fechados
 * são bloqueadas pelo DreMappingService (§2.4 + §2.8 do plano) — um
 * fechamento em `dre_period_closings` impede `effective_from ≤ closed_up_to_date`.
 *
 * `chart_of_account_id` só pode apontar para conta analítica (type =
 * 'analytical'); validação aplicada no FormRequest (prompt #5). O DB não
 * enforca porque não há `type` em chart_of_accounts nesta versão — vai
 * vir no prompt #2 junto com o importador do XLSX oficial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dre_mappings', function (Blueprint $table) {
            $table->id();

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

            $table->unsignedBigInteger('dre_management_line_id');
            $table->foreign('dre_management_line_id')
                ->references('id')
                ->on('dre_management_lines')
                ->restrictOnDelete();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by_user_id')->nullable();
            $table->string('deleted_reason', 500)->nullable();

            // UNIQUE composto para evitar duplicados no mesmo período.
            // cost_center_id nullable: MySQL/SQLite permitem múltiplos null,
            // dedup adicional fica no DreMappingService.
            $table->unique(
                ['chart_of_account_id', 'cost_center_id', 'effective_from'],
                'dre_mappings_unique_per_date'
            );

            $table->index(['chart_of_account_id', 'effective_from', 'effective_to'], 'dre_mappings_resolve_idx');
            $table->index('dre_management_line_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dre_mappings');
    }
};
