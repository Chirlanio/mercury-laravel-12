<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linhas de recebimento — uma por (item da ordem, qtd recebida nesta entrega).
 *
 * matched_movement_id: quando o receipt veio do matcher CIGAM, guarda o id
 * do registro `movements` que originou a linha. Garantido único — usado
 * pelo matcher para idempotência (se já existe receipt_item.matched_movement_id,
 * pula).
 *
 * unit_cost_cigam: custo unitário trazido do movement (campo cost_price).
 * Permite reconciliação visual contra o `unit_cost` cadastrado no item da
 * ordem — útil pra detectar discrepâncias entre o que foi pedido e o que
 * foi faturado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')
                ->constrained('purchase_order_receipts')
                ->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')
                ->constrained('purchase_order_items')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity_received');

            // Quando veio do matcher CIGAM
            $table->foreignId('matched_movement_id')
                ->nullable()
                ->constrained('movements')
                ->nullOnDelete();
            $table->decimal('unit_cost_cigam', 10, 2)->nullable();

            $table->timestamp('created_at')->nullable();

            // Garante idempotência do matcher: cada movement só pode ser
            // vinculado a um único receipt_item.
            $table->unique('matched_movement_id', 'idx_po_receipt_items_movement_unique');
            $table->index('purchase_order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_items');
    }
};
