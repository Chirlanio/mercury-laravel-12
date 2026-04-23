<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens de uma consignação — snapshot dos produtos enviados.
 *
 * FK NOT NULL para `products` + nullable para `product_variants` é a
 * materialização da regra M8: impossível registrar item cujo produto
 * não exista no catálogo. Snapshots de reference/ean/size/description
 * congelam o momento do cadastro — alterações futuras no catálogo não
 * afetam retrospectivamente.
 *
 * Quantidades resolvidas (returned + sold + lost) sempre ≤ quantity.
 * Status é derivado via ConsignmentItemStatus::derive() no service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained('consignments')->cascadeOnDelete();

            // Link opcional para o movement que gerou este item (code=20).
            // Nullable porque consignações draft podem ser cadastradas antes
            // da NF aparecer no sync CIGAM — o matcher reconcilia depois.
            $table->foreignId('movement_id')->nullable()->constrained('movements')->nullOnDelete();

            // Integração com catálogo — REGRA M8 (produto obrigatório no catálogo)
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Snapshots — congelados no cadastro
            $table->string('reference', 50);
            $table->string('barcode', 14)->nullable();
            $table->string('size_label', 20)->nullable();
            $table->string('size_cigam_code', 10)->nullable();
            $table->string('description')->nullable();

            // Quantidades (inteiros — peças, não frações)
            $table->unsignedInteger('quantity');
            $table->decimal('unit_value', 10, 2);
            $table->decimal('total_value', 10, 2);

            // Resolução — soma sempre ≤ quantity, validado no service
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->unsignedInteger('lost_quantity')->default(0);

            // Derivado — refreshDerivedStatus() no model
            $table->string('status', 20)->default('pending');

            // Motivo (para shrinkage / lost) — usado quando item é marcado
            // como perdido durante finalização
            $table->text('lost_reason')->nullable();

            $table->timestamps();

            $table->index(['consignment_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
    }
};
