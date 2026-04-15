<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordens de Compra — paridade com v1 (adms_purchase_order_controls), adaptado
 * para o domínio de varejo de moda do Grupo Meia Sola (season/collection +
 * size matrix em items).
 *
 * State machine em PurchaseOrderStatus:
 *   pending → invoiced → delivered (terminal)
 *         ├→ partial_invoiced → invoiced
 *         └→ cancelled → pending (reabertura, admin)
 *
 * Store scoping: usuário sem MANAGE_PURCHASE_ORDERS só vê ordens da própria
 * store (mesmo padrão de Vacancies).
 *
 * Soft delete manual (segue convenção de PersonnelMovement/Vacancy — não usa
 * trait SoftDeletes, delete manipulado pelo service com deleted_reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // === IDENTIFICAÇÃO ===
            // Número da ordem — unique, preenchido pelo usuário ou pela planilha.
            $table->string('order_number', 50)->unique();

            $table->string('short_description')->nullable();
            $table->string('season', 120);       // Ex: "INVERNO 2026"
            $table->string('collection', 120);   // Ex: "INVERNO 1"
            $table->string('release_name', 120); // Ex: "Lançamento 1"

            // === FORNECEDOR / LOJA / MARCA ===
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('store_id', 10); // Store code (mesmo padrão do projeto)
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();

            // === DATAS / PRAZOS ===
            $table->date('order_date');
            $table->date('predict_date')->nullable();
            $table->timestamp('delivered_at')->nullable(); // preenchido ao transitar para delivered

            // === CONDIÇÕES COMERCIAIS ===
            // String livre tipo "30/60/90/120" — padrão do varejo BR.
            // Um parser (Fase 3) converte isto em parcelas estruturadas em order_payments.
            $table->string('payment_terms_raw', 150)->nullable();

            // Flag para auto-gerar parcelas em order_payments na transição para INVOICED
            $table->boolean('auto_generate_payments')->default(false);

            // === STATUS ===
            $table->string('status', 30)->default('pending');

            // === OBSERVAÇÕES ===
            $table->text('notes')->nullable();

            // === AUDIT ===
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete manual
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // === ÍNDICES ===
            $table->index(['store_id', 'status']);
            $table->index(['status', 'predict_date']); // usado pelo scope overdue()
            $table->index(['supplier_id', 'status']);
            $table->index('brand_id');
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
