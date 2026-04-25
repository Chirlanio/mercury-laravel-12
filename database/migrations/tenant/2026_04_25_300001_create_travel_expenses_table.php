<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verbas de Viagem — paridade com v1 (adms_travel_expenses) adaptada ao
 * modelo v2 com state machine real, dual-status (solicitação + prestação),
 * CPF encriptado + cpf_hash determinístico, daily_rate persistido (não
 * hardcoded como na v1) e auditoria via histories table.
 *
 * Diferenças intencionais vs v1:
 *  - daily_rate persistido (v1 era hardcoded R$100 no Service)
 *  - days_count persistido (v1 calculava on-the-fly)
 *  - cpf_hash separa busca/dedup do CPF criptografado (padrão Coupons)
 *  - status separado de accountability_status (v1 tinha as 2 colunas mas só
 *    usava uma delas)
 *  - approver_user_id + cancelled_reason + finalized_at pra auditoria
 *  - store_code string referenciando stores.code (mesmo padrão Reversals)
 *  - dual payment (bank+account OU pix) é XOR validado no Service
 *
 * State machine em TravelExpenseStatus:
 *   draft → submitted → approved → finalized | cancelled
 *                    └─► rejected | cancelled
 *
 * Store scoping: usuário sem MANAGE_TRAVEL_EXPENSES só vê verbas onde é
 * solicitante (created_by_user_id) ou beneficiado (employee_id matching seu
 * próprio employee). Aplicado no scope do Model.
 *
 * Soft delete manual (padrão do projeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_expenses', function (Blueprint $table) {
            $table->id();

            // ULID público (substitui hash_id UUID v7 da v1)
            $table->ulid('ulid')->unique();

            // === BENEFICIÁRIO E LOJA ===
            // employee_id é o BENEFICIADO (quem viaja). created_by é o solicitante.
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            // Código da loja origem (Z421..Z457). Mantém padrão Reversals: FK
            // string para stores.code, sem foreign constraint formal.
            $table->string('store_code', 10);

            // === VIAGEM ===
            $table->string('origin', 120);
            $table->string('destination', 120);
            $table->date('initial_date');
            $table->date('end_date');

            // === VALOR DA VERBA ===
            // daily_rate persistido pra suportar mudança de política sem
            // recalcular registros antigos.
            $table->decimal('daily_rate', 8, 2);
            $table->unsignedInteger('days_count');
            $table->decimal('value', 10, 2); // = daily_rate * days_count

            // === CONTATO/CLIENTE (opcional — viagens internas não preenchem) ===
            $table->string('client_name', 150)->nullable();

            // === DOCUMENTO (CPF do beneficiário pra fins de pagamento) ===
            // Encriptado via cast `encrypted` no Model. Pode ser null em viagens
            // internas onde já há cadastro do colaborador.
            $table->text('cpf_encrypted')->nullable();
            // HMAC-SHA256 determinístico pra busca/dedup sem expor CPF
            $table->string('cpf_hash', 64)->nullable();

            // === PAGAMENTO ===
            // Dual payment — XOR validado no Service. Pelo menos um esperado
            // quando solicitação for submetida.
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('bank_branch', 10)->nullable();
            $table->string('bank_account', 20)->nullable();

            $table->foreignId('pix_type_id')->nullable()->constrained('type_key_pixs')->nullOnDelete();
            $table->text('pix_key_encrypted')->nullable(); // PIX key também encriptada

            // === DESCRIÇÃO ===
            $table->text('description'); // Justificativa/observação obrigatória
            $table->text('internal_notes')->nullable(); // Notas internas só do Financeiro

            // === STATUS DA SOLICITAÇÃO ===
            $table->string('status', 30)->default('draft'); // TravelExpenseStatus
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();

            // === STATUS DA PRESTAÇÃO DE CONTAS (paralelo) ===
            $table->string('accountability_status', 30)->default('pending'); // AccountabilityStatus
            $table->timestamp('accountability_submitted_at')->nullable();
            $table->timestamp('accountability_approved_at')->nullable();
            $table->timestamp('accountability_rejected_at')->nullable();
            $table->text('accountability_rejection_reason')->nullable();

            // === AUDIT ===
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete manual
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // === ÍNDICES ===
            $table->index(['store_code', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['accountability_status', 'end_date']);
            $table->index(['employee_id', 'status']);
            $table->index(['created_by_user_id', 'status']);
            $table->index('end_date'); // pra cron de overdue
            $table->index('cpf_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_expenses');
    }
};
