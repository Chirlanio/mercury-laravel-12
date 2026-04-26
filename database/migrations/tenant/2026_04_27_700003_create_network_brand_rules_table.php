<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whitelist de marcas aceitas por rede no contexto de matching de avariados.
 *
 * Comportamento (igual à v1 adms_network_brand_rules):
 *  - Se uma rede tem ≥1 row aqui: SOMENTE marcas listadas podem ser destino
 *    de match envolvendo lojas dessa rede (whitelist estrita)
 *  - Se uma rede NÃO tem nenhum row: aceita qualquer marca (default permissivo)
 *
 * Validação no DamagedProductMatchingService::areStoresBrandCompatible() é
 * BIDIRECIONAL — ambas as redes precisam aceitar a marca da contraparte pra
 * o match ser sugerido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_brand_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained('networks')->cascadeOnDelete();
            $table->string('brand_cigam_code', 30);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['network_id', 'brand_cigam_code'], 'uk_network_brand');
            $table->index('brand_cigam_code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_brand_rules');
    }
};
