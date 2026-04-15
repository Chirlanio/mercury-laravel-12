<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens de ordem de compra.
 *
 * Um registro por combinação (referência, tamanho). Não normaliza produtos:
 * dados do item (description, material, color) são gravados inline para
 * preservar histórico mesmo se o catálogo mudar depois. FK opcional para
 * products (pode ficar nula em imports legados sem catálogo).
 *
 * quantity_received é agregado da soma de receipt_items vinculados (Fase 2).
 * No MVP, começa em 0 e só será incrementado quando Receipts existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            // FK opcional para catálogo central (pode ser null para imports legados)
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // === DADOS DENORMALIZADOS (preserva histórico) ===
            $table->string('reference', 50);       // Referência do produto (ex: "00000819157")
            $table->string('size', 10);            // Tamanho (ex: "34", "M", "PP")
            $table->string('description');
            $table->string('material', 150)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('group_name', 200)->nullable();
            $table->string('subgroup_name', 200)->nullable();

            // === PRECIFICAÇÃO ===
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('markup', 5, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            // pricing_locked: se true, selling_price foi definido manualmente
            // e não deve ser recalculado automaticamente a partir de markup
            $table->boolean('pricing_locked')->default(false);

            // === QUANTIDADES ===
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);

            // === NOTA FISCAL (preenchido ao transitar a ordem para INVOICED) ===
            $table->string('invoice_number', 50)->nullable();
            $table->date('invoice_emission_date')->nullable();
            $table->date('confirmation_date')->nullable();

            $table->timestamps();

            // === ÍNDICES ===
            // Upsert do importador usa (purchase_order_id, reference, size) como chave
            $table->unique(['purchase_order_id', 'reference', 'size'], 'idx_po_items_unique');
            $table->index('reference');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
