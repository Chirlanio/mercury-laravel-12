<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consignações — envio de produtos para Cliente, Influencer ou
 * E-commerce com prazo de retorno. Paridade v1 (adms_consignments)
 * adaptada ao modelo v2:
 *
 *  - State machine 6 estados (ConsignmentStatus: draft → pending →
 *    partially_returned → overdue → completed | cancelled)
 *  - NF de saída (movement_code=20) e retorno (21) vinculadas a
 *    `movements` do CIGAM via snapshot composite (store_code +
 *    invoice_number + movement_date)
 *  - FK NOT NULL em products/variants — elimina produto "fantasma"
 *    (regra M8 do plano de escopo)
 *  - Bloqueio de cadastro por destinatário com overdue aberto via
 *    service (regra M9)
 *
 * Soft delete manual (padrão Returns/Reversals/PurchaseOrder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tipo — Cliente, Influencer ou E-commerce
            $table->string('type', 20);

            // Loja de origem da saída
            $table->foreignId('store_id')->constrained('stores');
            // Consultor responsável (obrigatório para type=cliente)
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // Destinatário — snapshot (só texto/documento; módulo Customers
            // ainda não existe, FK fica para M12). Adicionado sem constraint
            // para permitir migração simples quando customers for criado.
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('recipient_name', 200);
            $table->string('recipient_document', 18)->nullable();
            // Só dígitos — usado em dedup, M9 e filtros. Index explícito
            // porque varia a máscara de entrada.
            $table->string('recipient_document_clean', 14)->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('recipient_email')->nullable();

            // NF de saída (remessa — movement_code=20)
            $table->string('outbound_invoice_number', 20);
            $table->date('outbound_invoice_date');
            // Redundante com stores.code para simplificar lookup+snapshot
            $table->string('outbound_store_code', 10);
            $table->decimal('outbound_total_value', 12, 2)->default(0);
            $table->unsignedInteger('outbound_items_count')->default(0);

            // Totais agregados (recalculados por ConsignmentService::refreshTotals)
            $table->decimal('returned_total_value', 12, 2)->default(0);
            $table->unsignedInteger('returned_items_count')->default(0);
            $table->decimal('sold_total_value', 12, 2)->default(0);
            $table->unsignedInteger('sold_items_count')->default(0);
            $table->decimal('lost_total_value', 12, 2)->default(0);
            $table->unsignedInteger('lost_items_count')->default(0);

            // Prazo
            $table->date('expected_return_date');
            $table->unsignedSmallInteger('return_period_days')->default(7);

            // Status (state machine)
            $table->string('status', 30)->default('draft');

            // Timestamps do workflow
            $table->timestamp('issued_at')->nullable();       // draft → pending (NF emitida)
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();

            // Observações livres
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete manual (convenção do projeto)
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // Índices
            $table->index(['type', 'status']);
            $table->index(['store_id', 'status']);
            $table->index(['status', 'expected_return_date']);
            $table->index(['outbound_store_code', 'outbound_invoice_number']);
            $table->index(['recipient_document_clean', 'status'], 'idx_consign_recipient_status');
            $table->index('expected_return_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignments');
    }
};
