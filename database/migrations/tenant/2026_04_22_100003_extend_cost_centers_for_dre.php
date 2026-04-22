<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Estende `cost_centers` com 3 colunas de rastreabilidade do ERP:
 *   - reduced_code: opcional, curto, unique quando populado.
 *   - external_source: CIGAM/TAYLOR/ZZNET.
 *   - imported_at: timestamp da última importação.
 *
 * Não mexe nos dados atuais (24 linhas com códigos 421..457) — a limpeza
 * desses CCs legados (que na verdade são stores.code) fica para o prompt
 * #3 do cronograma, quando o importador oficial do grupo 8 do Excel
 * substituir pelos 11 CCs departamentais reais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->string('reduced_code', 20)->nullable()->after('code');
            $table->string('external_source', 20)->nullable()->after('is_active');
            $table->timestamp('imported_at')->nullable()->after('external_source');

            $table->unique('reduced_code', 'cost_centers_reduced_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropUnique('cost_centers_reduced_code_unique');
            $table->dropColumn(['reduced_code', 'external_source', 'imported_at']);
        });
    }
};
