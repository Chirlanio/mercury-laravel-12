<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Fechamento de períodos da DRE (resposta #6 do usuário). Cada linha
 * representa um fechamento: `closed_up_to_date` é inclusivo (fechar
 * Janeiro/2026 grava `2026-01-31`).
 *
 * Regra enforced pelo DreMappingService / DreActualsImporter:
 *   - mapping com `effective_from ≤ MAX(closed_up_to_date)` é rejeitado.
 *   - import manual de dre_actuals com `entry_date ≤ MAX(closed_up_to_date)`
 *     é rejeitado.
 *   - projetores OrderPayment/Sale continuam funcionando (fonte canônica),
 *     mas marcam `dre_actuals.reported_in_closed_period=true`.
 *
 * Reabertura via `reopened_by_user_id` + `reopened_at` + `reopen_reason`.
 * Ao reabrir, `DrePeriodClosingService::reopen()` também apaga os
 * snapshots do período e gera relatório consolidado (§2.8 do plano).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dre_period_closings', function (Blueprint $table) {
            $table->id();

            $table->date('closed_up_to_date');

            $table->unsignedBigInteger('closed_by_user_id');
            $table->timestamp('closed_at');

            $table->unsignedBigInteger('reopened_by_user_id')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Sem unique em closed_up_to_date — uma mesma data pode ser
            // fechada múltiplas vezes ao longo do tempo (fechou → reabriu
            // → refechou). O DrePeriodClosingService garante que só 1
            // fechamento ativo (reopened_at IS NULL) existe por data.
            $table->index('closed_up_to_date');
            $table->index('reopened_at');
            $table->index(['closed_up_to_date', 'reopened_at'], 'dre_period_closings_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dre_period_closings');
    }
};
