<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige a FK `purchase_orders.brand_id` para apontar pra `product_brands`
 * (sincronizada do CIGAM via ProductSyncService), em vez de `brands` (tabela
 * legacy com apenas 6 marcas hardcoded).
 *
 * Este é o conceito correto: a marca do pedido (LUIZA BARCELOS, VICENZA, etc)
 * vem do catálogo sincronizado do ERP, não do cadastro manual.
 *
 * O módulo é novo e não tem dados em produção (purchase_orders está vazia),
 * então é seguro dropar e recriar a FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreign('brand_id')
                ->references('id')
                ->on('product_brands')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->nullOnDelete();
        });
    }
};
