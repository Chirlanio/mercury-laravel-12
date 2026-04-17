<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens de estorno parcial por produto. Populado apenas quando
 * `reversals.type = partial` e `reversals.partial_mode = by_item`.
 *
 * Cada linha referencia uma linha original em `movements` (movement_id)
 * e guarda snapshot dos dados do produto no momento do estorno. A soma
 * de `reversal_items.amount` de um reversal deve bater com
 * `reversals.amount_reversal` (invariante checada pelo service).
 *
 * `movement_id` é nullable porque `movements` pode ser resincronizada
 * e apagar linhas antigas — perdemos o vínculo mas mantemos o snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reversal_id')->constrained('reversals')->cascadeOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('movements')->nullOnDelete();

            // Snapshot do produto — estável mesmo se `movements` for recriada.
            $table->string('barcode', 50)->nullable();
            $table->string('ref_size', 50)->nullable();
            $table->string('product_name', 255)->nullable();

            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2); // quantity * unit_price

            $table->timestamps();

            $table->index('reversal_id');
            $table->index('movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversal_items');
    }
};
