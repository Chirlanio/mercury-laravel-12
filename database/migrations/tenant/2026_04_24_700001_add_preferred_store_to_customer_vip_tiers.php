<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programa MS Life — adiciona a loja de preferência do cliente VIP.
 *
 * Calculada a partir do agregado das movimentações do cliente nas lojas da
 * rede Meia Sola: priorizando maior faturamento líquido → maior número de
 * NFs (tickets) → maior quantidade de itens. String simples (store_code)
 * sem FK para evitar cascade em purges do CIGAM — o serviço resolve o nome
 * da loja no payload para a UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_vip_tiers', function (Blueprint $table) {
            $table->string('preferred_store_code', 10)->nullable()->after('total_orders');
            $table->index('preferred_store_code');
        });
    }

    public function down(): void
    {
        Schema::table('customer_vip_tiers', function (Blueprint $table) {
            $table->dropIndex(['preferred_store_code']);
            $table->dropColumn('preferred_store_code');
        });
    }
};
