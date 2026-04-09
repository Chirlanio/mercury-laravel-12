<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacation_period_id')->constrained('vacation_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('store_id', 10)->nullable(); // Loja no momento da solicitação
            $table->date('date_start');
            $table->date('date_end');
            $table->date('date_return'); // Próximo dia útil após fim
            $table->unsignedSmallInteger('days_quantity'); // 5-30 dias
            $table->unsignedTinyInteger('installment')->default(1); // Parcela 1, 2 ou 3
            $table->boolean('sell_allowance')->default(false); // Abono pecuniário
            $table->unsignedSmallInteger('sell_days')->default(0); // Dias vendidos nesta parcela
            $table->boolean('advance_13th')->default(false); // Adiantamento 13º
            $table->date('payment_deadline')->nullable(); // 2 dias úteis antes (Art. 145)
            $table->boolean('default_days_override')->default(false); // Dias fora do padrão
            $table->string('override_reason', 255)->nullable(); // Justificativa

            // Status
            $table->string('status', 30)->default('draft');

            // Aprovações
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable();
            $table->text('manager_notes')->nullable();
            $table->foreignId('hr_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_approved_at')->nullable();
            $table->text('hr_notes')->nullable();

            // Rejeição
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Cancelamento
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Controle
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('employee_acknowledged_at')->nullable();
            $table->unsignedSmallInteger('previous_employee_status')->nullable(); // Para restauração

            // Retroativo
            $table->boolean('is_retroactive')->default(false);
            $table->string('retroactive_reason', 500)->nullable();

            // Auditoria
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('delete_reason')->nullable();

            $table->index(['employee_id', 'status']);
            $table->index(['vacation_period_id', 'status']);
            $table->index(['store_id', 'status']);
            $table->index(['date_start', 'date_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacations');
    }
};
