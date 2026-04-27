<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona damaged_size em damaged_products.
 *
 * Necessário pra que matching de damaged_complement seja correto:
 * dois produtos avariados em pés opostos só fazem par BOM se forem
 * do MESMO tamanho. Antes a engine cruzava só pelos pés (bug latente
 * que poderia gerar matches inválidos).
 *
 * Obrigatório quando damaged_foot é left/right/both (informa o tamanho
 * dos pés afetados); ignorado quando damaged_foot=na (não-calçado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damaged_products', function (Blueprint $table) {
            $table->string('damaged_size', 20)->nullable()->after('damaged_foot');
        });
    }

    public function down(): void
    {
        Schema::table('damaged_products', function (Blueprint $table) {
            $table->dropColumn('damaged_size');
        });
    }
};
