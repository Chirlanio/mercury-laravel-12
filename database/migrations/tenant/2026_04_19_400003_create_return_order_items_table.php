<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens devolvidos em uma solicitação de return_order.
 *
 * Populado sempre (todo return tem pelo menos 1 item). Cada linha
 * referencia uma linha original em `movements` (movement_id, nullable
 * por resilência a re-sync) e guarda snapshot completo dos dados do
 * produto no momento da solicitação.
 *
 * Soma dos `subtotal` de todos os itens = `return_orders.amount_items`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_order_id')->constrained('return_orders')->cascadeOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('movements')->nullOnDelete();

            // Snapshot do produto — estável mesmo se `movements` for recriada.
            $table->string('reference', 50)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('barcode', 50)->nullable();
            $table->string('product_name', 255)->nullable();

            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2); // quantity * unit_price

            $table->timestamps();

            $table->index('return_order_id');
            $table->index('movement_id');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_order_items');
    }
};
