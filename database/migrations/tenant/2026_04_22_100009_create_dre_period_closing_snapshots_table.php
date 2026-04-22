<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Imutabilidade de períodos fechados (resposta #25 do usuário). No fechamento,
 * `DrePeriodClosingService::close()` computa a matriz live para os 3 escopos
 * (Geral/Rede/Loja) × meses dentro do período × linhas da DRE e inserta um
 * snapshot. `DreMatrixService` lê daqui quando o filtro cai em período
 * fechado.
 *
 * Ao reabrir, os snapshots do período são apagados. Refechar recomputa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dre_period_closing_snapshots', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('dre_period_closing_id');
            $table->foreign('dre_period_closing_id')
                ->references('id')
                ->on('dre_period_closings')
                ->cascadeOnDelete();

            // scope: 'GENERAL' / 'NETWORK' / 'STORE'
            $table->string('scope', 10);
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->unsignedBigInteger('dre_management_line_id');
            $table->foreign('dre_management_line_id')
                ->references('id')
                ->on('dre_management_lines')
                ->restrictOnDelete();

            $table->char('year_month', 7); // 'YYYY-MM'

            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('budget_amount', 15, 2)->default(0);

            $table->timestamps();

            $table->index(
                ['dre_period_closing_id', 'scope', 'scope_id', 'year_month'],
                'dre_pcs_lookup_idx'
            );
            $table->index(['scope', 'year_month'], 'dre_pcs_scope_ym_idx');
            $table->index('dre_management_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dre_period_closing_snapshots');
    }
};
