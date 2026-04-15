<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recebimentos de ordens de compra.
 *
 * Um receipt representa um evento de recebimento físico — pode ser:
 *  - source='manual': usuário registrou na UI (informa NF e qtds)
 *  - source='cigam_match': criado automaticamente pelo
 *    PurchaseOrderCigamMatcherService quando achou um Movement com
 *    movement_code=17 (Ordem de Compra) + entry_exit='E' que casa com a ordem
 *
 * Permite recebimentos parciais — cada receipt é uma "entrega" da NF do
 * fornecedor. O agregado `quantity_received` em purchase_order_items é
 * derivado da soma de todos os receipt_items vinculados àquele item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            $table->timestamp('received_at');
            $table->string('invoice_number', 50)->nullable();
            $table->text('notes')->nullable();

            // 'manual' | 'cigam_match'
            $table->string('source', 20)->default('manual');

            // Sync batch que gerou o match (rastreio em caso de re-sync)
            $table->uuid('matched_sync_batch_id')->nullable();

            // Null quando source='cigam_match' (sem usuário)
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['purchase_order_id', 'received_at']);
            $table->index('invoice_number');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipts');
    }
};
