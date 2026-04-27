<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitações de remanejo (transferência entre lojas).
 *
 * Diferente do módulo `transfers` (que registra o MOVIMENTO físico),
 * `relocations` registra a SOLICITAÇÃO feita por planejamento/logística
 * com itens granulares (qty solicitada/separada/recebida por produto).
 *
 * State machine em RelocationStatus (9 estados):
 *   draft → requested → approved → in_separation → in_transit →
 *     completed | partial
 *   * → rejected (terminal apenas a partir de requested)
 *   * → cancelled (qualquer estado pré-in_transit)
 *
 * Acoplamento com Transfer:
 *  - Quando transita para in_transit (loja origem confirma envio com NF),
 *    o RelocationTransitionService cria um Transfer com transfer_type=
 *    'relocation' e linka via FK transfer_id.
 *  - Antes de in_transit, transfer_id é NULL (solicitação interna).
 *
 * Reconciliação CIGAM:
 *  - movement_code=5 + entry_exit='S' = saída na origem (auditoria)
 *  - movement_code=5 + entry_exit='E' = entrada na destino (transição
 *    automática para `completed` via RelocationCigamMatcher).
 *  - cigam_matched_at registra quando o matcher casou a NF.
 *
 * Store scoping: usuário sem MANAGE_RELOCATIONS só vê remanejos onde sua
 * loja seja origem OU destino (filtro bilateral, diferente da v1 que
 * filtrava só por origem).
 *
 * Soft delete manual (padrão do projeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relocations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            // === IDENTIFICAÇÃO E LOJAS ===
            $table->foreignId('relocation_type_id')->constrained('relocation_types')->restrictOnDelete();
            $table->foreignId('origin_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('destination_store_id')->constrained('stores')->restrictOnDelete();

            // Descrição livre (paridade com v1 relocation_name)
            $table->string('title', 200)->nullable();
            $table->text('observations')->nullable();

            // === PRIORIDADE E PRAZO ===
            $table->string('priority', 20)->default('normal'); // RelocationPriority enum
            $table->unsignedSmallInteger('deadline_days')->nullable(); // Prazo em dias da aprovação

            // === STATUS / WORKFLOW ===
            $table->string('status', 30)->default('draft'); // RelocationStatus enum
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('separated_at')->nullable();   // momento de in_separation → in_transit
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();

            // === NF DE TRANSFERÊNCIA (obrigatória em in_separation → in_transit) ===
            $table->string('invoice_number', 50)->nullable();
            $table->date('invoice_date')->nullable();

            // === INTEGRAÇÕES ===
            // FK reverso pro Transfer físico criado em in_transit
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            // Timestamp do match CIGAM (movement_code=5 + entry_exit='E')
            $table->timestamp('cigam_matched_at')->nullable();
            // Hook Helpdesk fail-safe (rejeição → ticket no depto Logística)
            $table->foreignId('helpdesk_ticket_id')->nullable();

            // === AUDIT ===
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('separated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete manual (padrão Reversals/PurchaseOrders)
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // === ÍNDICES ===
            $table->index(['origin_store_id', 'status']);
            $table->index(['destination_store_id', 'status']);
            $table->index(['status', 'created_at']);    // dashboard / listagem
            $table->index(['status', 'approved_at']);   // overdue alert (deadline a partir do approved_at)
            $table->index('priority');
            $table->index('cigam_matched_at');           // command de match
            $table->index(['invoice_number', 'destination_store_id'], 'idx_relocations_nf_dest');
            $table->index('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relocations');
    }
};
