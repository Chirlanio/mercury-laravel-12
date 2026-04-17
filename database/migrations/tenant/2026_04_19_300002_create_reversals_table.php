<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitações de estorno (Reversals) — paridade com v1 (adms_estornos)
 * adaptado para o modelo de dados da v2, onde a fonte de verdade das vendas
 * é a tabela `movements` (movement_code=2) e não uma tabela `sale_items`.
 *
 * Fluxo:
 *  1. Usuário informa NF/cupom (`invoice_number`).
 *  2. Backend busca em `movements` e persiste snapshot dos dados agregados
 *     (store_code, movement_date, cpf_consultant, sale_total).
 *  3. Quando estorno parcial por produto, cada item selecionado vira uma
 *     linha em `reversal_items` vinculada ao `movement_id`.
 *
 * State machine em ReversalStatus (6 estados):
 *   pending_reversal → pending_authorization → authorized →
 *   pending_finance → reversed (terminal) | cancelled (terminal)
 *
 * Store scoping: usuário sem MANAGE_REVERSALS só vê estornos da própria
 * store_code (mesmo padrão de Vacancies e PurchaseOrders).
 *
 * Soft delete manual (convenção do projeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversals', function (Blueprint $table) {
            $table->id();

            // === LOOKUP / SNAPSHOT DA VENDA ===
            // Chave principal: número da NF/cupom fiscal usado para buscar
            // em `movements` (movement_code=2). Mantido como string porque
            // alguns canais emitem documentos alfanuméricos.
            $table->string('invoice_number', 50);

            // Snapshot do cabeçalho da venda. Guardamos para estabilidade
            // histórica caso a linha em `movements` seja re-sincronizada.
            $table->string('store_code', 10); // Código da loja (Z421, Z457…)
            $table->date('movement_date');
            $table->string('cpf_customer', 14)->nullable();
            $table->string('customer_name', 250);
            $table->string('cpf_consultant', 14)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // Total da NF apurado na criação (SUM(realized_value) dos
            // movements com o mesmo invoice_number). Serve como teto para
            // valores de estorno (nunca estornar mais do que o vendido).
            $table->decimal('sale_total', 12, 2);

            // === TIPO E VALORES ===
            // total  → estorna sale_total inteiro
            // partial → estorna parte; partial_mode define como
            $table->string('type', 20);                    // ReversalType enum
            $table->string('partial_mode', 20)->nullable(); // ReversalPartialMode (apenas quando type=partial)

            // amount_original: valor base (tipicamente igual a sale_total
            // mas preservado em coluna própria por auditoria).
            $table->decimal('amount_original', 12, 2);

            // amount_correct: preenchido só em partial com mode=by_value.
            // Representa o valor que deveria ter sido cobrado.
            $table->decimal('amount_correct', 12, 2)->nullable();

            // amount_reversal: valor efetivo do estorno. Calculado pelo
            // ReversalService (total => sale_total; by_value => original-correct;
            // by_item => soma dos reversal_items).
            $table->decimal('amount_reversal', 12, 2);

            // === STATUS / WORKFLOW ===
            $table->string('status', 30)->default('pending_reversal'); // ReversalStatus enum
            $table->foreignId('reversal_reason_id')->constrained('reversal_reasons')->restrictOnDelete();
            $table->date('expected_refund_date')->nullable(); // Data prometida pela adquirente
            $table->timestamp('reversed_at')->nullable();      // Quando transitou para 'reversed'
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();

            // === PAGAMENTO ORIGINAL ===
            $table->foreignId('payment_type_id')->nullable()->constrained('payment_types')->nullOnDelete();
            $table->string('payment_brand', 50)->nullable();          // Bandeira do cartão (VISA, MASTER…)
            $table->unsignedSmallInteger('installments_count')->nullable();
            $table->string('nsu', 50)->nullable();
            $table->string('authorization_code', 50)->nullable();

            // === PIX (quando payment_type for PIX) ===
            $table->string('pix_key_type', 30)->nullable();   // cpf, cnpj, email, phone, random
            $table->string('pix_key', 255)->nullable();
            $table->string('pix_beneficiary', 255)->nullable();
            $table->foreignId('pix_bank_id')->nullable()->constrained('banks')->nullOnDelete();

            // === OBSERVAÇÕES ===
            $table->text('notes')->nullable();

            // === INTEGRAÇÕES ===
            // Timestamp para idempotência do push CIGAM (Fase 4).
            $table->timestamp('synced_to_cigam_at')->nullable();

            // Referência ao ticket de Helpdesk aberto automaticamente pelo
            // hook da Fase 4 (quando transitar para pending_authorization).
            $table->foreignId('helpdesk_ticket_id')->nullable();

            // === AUDIT + APROVAÇÕES ===
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete manual (padrão do projeto — PersonnelMovement, Vacancy, PurchaseOrder)
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // === ÍNDICES ===
            $table->index(['store_code', 'status']);
            $table->index(['status', 'created_at']);          // dashboards e listagens
            $table->index(['invoice_number', 'store_code']);   // lookup reverso
            $table->index('movement_date');
            $table->index('synced_to_cigam_at');               // para o command de push

            // Dedup: bloqueamos duplicatas via ReversalService::ensureNoDuplicate
            // em vez de unique composto. Motivo: MySQL trata múltiplos NULLs em
            // unique composite como distintos, então `deleted_at` (NULL para
            // ativos) não filtra corretamente. Este índice serve só para
            // performance da checagem no service.
            $table->index(
                ['invoice_number', 'store_code', 'amount_original'],
                'idx_reversal_dedup_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversals');
    }
};
