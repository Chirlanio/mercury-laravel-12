<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de lookup de códigos de barras EAN-13 internos por (reference, size).
 *
 * Idempotente: a mesma combinação (reference, size) sempre tem o mesmo
 * barcode. Substitui as 2 tabelas auxiliares do v1 (_products + _variants)
 * por uma única tabela mais simples.
 *
 * O EAN-13 é gerado pelo EanGeneratorService usando prefixo "2" (interno,
 * não colide com fornecedor real GS1) + ID da row (5 dígitos) + 6 zeros
 * + check digit. Como o id é unique e o gerador é determinístico, a
 * combinação (reference, size) → barcode é estável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50);
            $table->string('size', 10);
            $table->string('barcode', 13)->unique();
            $table->timestamps();

            $table->unique(['reference', 'size'], 'idx_po_barcodes_ref_size_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_barcodes');
    }
};
