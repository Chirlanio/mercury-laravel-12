<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VacancyOpening module (paridade com v1 — adms_vacancy_opening).
 *
 * Tipos de solicitação: substitution, headcount_increase, floater.
 * Ciclo: open → processing → in_admission → finalized | cancelled.
 *
 * Integração bidirecional com personnel_movements:
 *  - origin_movement_id aponta para o desligamento que originou uma vaga de
 *    substituição (preenchido automaticamente pelo listener que escuta
 *    PersonnelMovementCreated quando type=dismissal && open_vacancy=true).
 *  - Ao finalizar a vaga, NÃO cria um movimento de admissão — cria um
 *    Employee em estado "Pendente" (pré-cadastro), e hired_employee_id
 *    aponta para esse funcionário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();

            // === IDENTIFICAÇÃO DA VAGA ===
            $table->string('store_id', 10); // Store code (mesmo padrão de employees/personnel_movements)
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained('work_schedules')->nullOnDelete();

            // Tipo de solicitação: substitution | headcount_increase | floater
            $table->string('request_type', 20);

            // Substituição: FK nullable para o funcionário a ser substituído.
            // Obrigatório em regra de negócio quando request_type='substitution'.
            $table->foreignId('replaced_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // Vínculo com o desligamento originador (quando a vaga nasce
            // automaticamente de um PersonnelMovement de tipo dismissal).
            $table->foreignId('origin_movement_id')->nullable()->constrained('personnel_movements')->nullOnDelete();

            // === STATUS / CICLO DE VIDA ===
            // open | processing | in_admission | finalized | cancelled
            $table->string('status', 20)->default('open');

            // === RECRUTAMENTO ===
            // Recrutador atribuído (User com permissão MANAGE_VACANCIES).
            // Obrigatório ao transicionar open → processing.
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();

            // === SLA ===
            $table->unsignedInteger('predicted_sla_days'); // dias previstos para fechamento
            $table->unsignedInteger('effective_sla_days')->nullable(); // calculado ao finalizar
            $table->date('delivery_forecast')->nullable(); // created_at + predicted_sla_days (editável)
            $table->date('closing_date')->nullable(); // data em que transitou para finalized/cancelled

            // === CONTRATAÇÃO ===
            // Ao finalizar, aponta para o Employee criado em estado Pendente (pré-cadastro).
            $table->foreignId('hired_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('date_admission')->nullable();

            // === ENTREVISTAS (paridade v1) ===
            $table->text('interview_hr')->nullable();
            $table->text('evaluators_hr')->nullable();
            $table->text('interview_leader')->nullable();
            $table->text('evaluators_leader')->nullable();

            // === OBSERVAÇÕES ===
            $table->text('comments')->nullable();

            // === AUDIT ===
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // === ÍNDICES ===
            $table->index(['store_id', 'status']);
            $table->index(['status', 'delivery_forecast']); // usado pelo scope overdue()
            $table->index(['request_type', 'status']);
            $table->index('recruiter_id');
            $table->index('origin_movement_id');
            $table->index('hired_employee_id');
            $table->index('replaced_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
