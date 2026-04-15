<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `product_size_id` em `purchase_order_items`.
 *
 * O campo `size` (string) continua existindo e armazena o label original
 * da planilha ("33", "M", "33/34"). O novo `product_size_id` aponta pro
 * tamanho oficial do catálogo CIGAM resolvido via PurchaseOrderSizeMapping.
 *
 * Denormalização intencional: o string serve pra UI/auditoria (mostra o
 * label original importado), o FK serve pra análises/relatórios que
 * precisam cruzar com o catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'product_size_id')) {
                $table->foreignId('product_size_id')
                    ->nullable()
                    ->after('size')
                    ->constrained('product_sizes')
                    ->nullOnDelete();
                $table->index('product_size_id', 'idx_po_items_product_size_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'product_size_id')) {
                $table->dropForeign(['product_size_id']);
                $table->dropIndex('idx_po_items_product_size_id');
                $table->dropColumn('product_size_id');
            }
        });
    }
};
