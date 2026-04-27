<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens (linhas) de um remanejo.
 *
 * Cada linha representa uma combinação produto × tamanho com 3 quantidades
 * separadas para rastrear o ciclo:
 *  - qty_requested  : pedido inicial (planejamento ou loja destino)
 *  - qty_separated  : separado de fato pela loja origem (≤ requested)
 *  - qty_received   : recebido pela loja destino (≤ separated)
 *  - matched_quantity: agregado pelo RelocationCigamMatcher via barcode
 *                      em movements code=5 + entry_exit='E'
 *
 * Status do item é derivado (não persistido):
 *   COMPLETED se qty_received >= qty_requested
 *   PARTIAL   se 0 < qty_received < qty_requested
 *   PENDING   se qty_received == 0
 *
 * `product_id` é nullable (igual damaged_products) para acomodar
 * produto não-sincronizado no catálogo. `barcode` e `product_reference`
 * são sempre preenchidos para rastreio.
 *
 * `reason_code` categoriza divergência quando recebido < separado
 * (DAMAGE, MISSING, EXTRA, OTHER). Usado pelo dashboard de causas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relocation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relocation_id')->constrained('relocations')->cascadeOnDelete();

            // === IDENTIFICAÇÃO DO PRODUTO ===
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_reference', 100); // SKU/referência (sempre preenchida)
            $table->string('product_name', 255)->nullable();
            $table->string('product_color', 80)->nullable();
            $table->string('size', 20)->nullable();
            $table->string('barcode', 50)->nullable();

            // === QUANTIDADES ===
            $table->unsignedInteger('qty_requested')->default(1);
            $table->unsignedInteger('qty_separated')->default(0);
            $table->unsignedInteger('qty_received')->default(0);
            $table->unsignedInteger('matched_quantity')->default(0); // CIGAM matcher

            // === DIVERGÊNCIA (preenchido se recebido < separado) ===
            $table->string('reason_code', 30)->nullable();
            $table->text('observations')->nullable();

            $table->timestamps();

            // === ÍNDICES ===
            $table->index('relocation_id');
            $table->index('product_id');
            $table->index('barcode');
            $table->index('product_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relocation_items');
    }
};
