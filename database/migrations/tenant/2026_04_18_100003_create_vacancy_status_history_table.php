<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail de transições de status da vaga. Uma linha por transição,
 * gravada pelo VacancyTransitionService. Usado pela timeline do modal de
 * detalhes (StandardModal.Timeline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();

            // from_status é nullable na linha de criação da vaga (estado inicial).
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);

            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['vacancy_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_status_history');
    }
};
