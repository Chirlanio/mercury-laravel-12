<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail de transições de status de verbas de viagem. Uma linha por
 * transição, gravada pelo TravelExpenseTransitionService. Captura tanto
 * transições do TravelExpenseStatus quanto do AccountabilityStatus —
 * `kind` distingue.
 *
 *   kind = 'expense'         → from_status/to_status são valores de
 *                              TravelExpenseStatus
 *   kind = 'accountability'  → from_status/to_status são valores de
 *                              AccountabilityStatus
 *
 * `from_status` é null apenas na linha inicial (criação do registro).
 *
 * Usado pela timeline do modal de detalhes (StandardModal.Timeline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_expense_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_expense_id')->constrained('travel_expenses')->cascadeOnDelete();

            $table->string('kind', 20)->default('expense'); // 'expense' | 'accountability'
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['travel_expense_id', 'created_at'], 'idx_te_history_expense_created');
            $table->index(['travel_expense_id', 'kind'], 'idx_te_history_expense_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_expense_status_histories');
    }
};
