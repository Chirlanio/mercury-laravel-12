<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivô — quais itens foram devolvidos em cada evento de retorno e em
 * que quantidade. Permite retorno parcial: um item com quantity=10
 * pode ter 3 neste evento e 7 em um evento futuro.
 *
 * Restrição de negócio (ConsignmentReturnService::register):
 *  - consignment_item.consignment_id === consignment_return.consignment_id
 *    (a composição sai/volta na mesma consignação — regra M1)
 *  - sum(quantity do mesmo consignment_item em todos os returns) +
 *    sold_quantity + lost_quantity <= consignment_item.quantity
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_return_id')->constrained('consignment_returns')->cascadeOnDelete();
            $table->foreignId('consignment_item_id')->constrained('consignment_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_value', 10, 2);
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();

            $table->unique(['consignment_return_id', 'consignment_item_id'], 'idx_return_item_unique');
            $table->index('consignment_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_return_items');
    }
};
