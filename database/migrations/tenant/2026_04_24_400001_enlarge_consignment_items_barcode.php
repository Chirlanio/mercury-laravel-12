<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aumenta consignment_items.barcode de 14 para 32 caracteres.
 *
 * No padrão Mercury/CIGAM, `product_variants.barcode` armazena a
 * concatenação ref+size (ex: 'A1340000010002U35' — 17 chars), NÃO
 * o EAN-13 puro. Quando o ConsignmentItem recebe esse valor via
 * lookup de NF, a coluna original de 14 chars estourava a validação.
 *
 * 32 chars acomoda variações operacionais sem ser excessivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_items', function (Blueprint $table) {
            $table->string('barcode', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('consignment_items', function (Blueprint $table) {
            $table->string('barcode', 14)->nullable()->change();
        });
    }
};
