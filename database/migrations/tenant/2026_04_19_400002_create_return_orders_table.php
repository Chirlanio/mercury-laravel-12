<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitações de devolução/troca do e-commerce — paridade com v1
 * (adms_returns) adaptado ao modelo v2:
 *  - Lookup da venda via `movements.invoice_number` (store_code='Z441'
 *    por padrão — configurável por tenant).
 *  - Categorização obrigatória do motivo via enum ReturnReasonCategory
 *    + FK opcional para return_reasons (motivo específico livre).
 *  - State machine de 6 estados em ReturnStatus.
 *
 * Nomenclatura: `return_orders` / `ReturnOrder` — "Return" é keyword
 * do PHP; usamos sufixo "Order" seguindo o padrão de PurchaseOrder.
 *
 * Store scoping: ausência de MANAGE_RETURNS ativa filtro por store_code.
 * No e-commerce puro, esse filtro tipicamente é irrelevante (todas as
 * vendas são Z441), mas mantemos o scoping por consistência.
 *
 * Soft delete manual (convenção do projeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_orders', function (Blueprint $table) {
            $table->id();

            // === LOOKUP / SNAPSHOT DA VENDA ===
            // Número da NF/cupom — obrigatório. Permite lookup em movements
            // para puxar os itens elegíveis.
            $table->string('invoice_number', 50);

            // Snapshot — garante estabilidade histórica caso `movements`
            // seja re-sincronizado.
            $table->string('store_code', 10);
            $table->date('movement_date');
            $table->string('cpf_customer', 14)->nullable();
            $table->string('customer_name', 250);
            $table->string('cpf_consultant', 14)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // Total da NF (somatório dos movements na criação).
            $table->decimal('sale_total', 12, 2);

            // === TIPO E VALORES ===
            // troca | estorno | credito (ReturnType enum)
            $table->string('type', 20);

            // amount_items: somatório dos subtotais dos itens devolvidos.
            // Sempre populado, independente do type.
            $table->decimal('amount_items', 12, 2)->default(0);

            // refund_amount: valor efetivo a ser estornado/creditado ao
            // cliente (só aplicável quando type=estorno/credito).
            $table->decimal('refund_amount', 12, 2)->nullable();

            // === STATUS / WORKFLOW ===
            $table->string('status', 30)->default('pending');

            // Categorização do motivo (enum ReturnReasonCategory) — permite
            // dashboard agregado por categoria sem JOIN com return_reasons.
            $table->string('reason_category', 30);

            // Motivo específico (FK para catálogo). Nullable pra aceitar
            // casos onde só a categoria é suficiente (ex: "outro").
            $table->foreignId('return_reason_id')->nullable()->constrained('return_reasons')->nullOnDelete();

            // === LOGÍSTICA REVERSA ===
            // Campo livre — o operacional pode anotar Correios/Loggi/etc.
            // Sem integração automática nesta fase.
            $table->string('reverse_tracking_code', 100)->nullable();

            // === TIMESTAMPS DO WORKFLOW ===
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();

            // === OBSERVAÇÕES ===
            $table->text('notes')->nullable();

            // === AUDIT ===
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete manual (padrão PurchaseOrder/Vacancy/Reversal)
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // === ÍNDICES ===
            $table->index(['store_code', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['invoice_number', 'store_code']);
            $table->index('movement_date');
            $table->index('type');
            $table->index('reason_category');
            // Dedup lookup — checagem via service (mesma limitação MySQL
            // do Reversals com soft delete + unique composite).
            $table->index(['invoice_number', 'store_code', 'type'], 'idx_return_dedup_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_orders');
    }
};
