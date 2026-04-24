<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programa MS Life — separa o ano da LISTA do ano de APURAÇÃO.
 *
 * A lista VIP do ano N (year) é montada com base no faturamento do ano N-1
 * (revenue_year). Por exemplo: lista de 2026 considera as compras de 2025;
 * lista de 2025 considera as compras de 2024.
 *
 * Coluna nullable porque registros gerados antes dessa migração não tinham
 * o campo. O service preenche sempre que rodar uma nova classificação;
 * o controller faz fallback (year - 1) ao expor para a UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_vip_tiers', function (Blueprint $table) {
            $table->unsignedSmallInteger('revenue_year')->nullable()->after('preferred_store_code');
        });
    }

    public function down(): void
    {
        Schema::table('customer_vip_tiers', function (Blueprint $table) {
            $table->dropColumn('revenue_year');
        });
    }
};
