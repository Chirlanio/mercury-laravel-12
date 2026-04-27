<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refator do schema de damaged_products para acomodar 3 mudanças de UX:
 *
 *  1. brand_name: snapshot do NOME da marca (UI mostra nome, não cigam_code).
 *     brand_cigam_code mantido pro matching engine.
 *  2. product_size REMOVIDO: campo redundante.
 *  3. mismatched_foot + actual_size + expected_size SUBSTITUÍDOS por
 *     mismatched_left_size + mismatched_right_size (semântica mais limpa:
 *     2 cliques no UI, 1 em cada linha de pé). Engine de matching cruza
 *     A.left=B.right E A.right=B.left.
 *
 * Módulo é novo em produção (rodou só em dev nos últimos dias) — drop dos
 * campos antigos é seguro, sem dados a preservar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damaged_products', function (Blueprint $table) {
            $table->string('brand_name', 100)->nullable()->after('brand_cigam_code');
            $table->string('mismatched_left_size', 20)->nullable()->after('is_damaged');
            $table->string('mismatched_right_size', 20)->nullable()->after('mismatched_left_size');
        });

        // Drop colunas antigas em uma segunda Schema::table — alguns drivers
        // não suportam add+drop no mesmo callback.
        Schema::table('damaged_products', function (Blueprint $table) {
            // Drop indexes antes de dropar colunas (defensivo)
            try { $table->dropIndex(['is_mismatched', 'is_damaged']); } catch (\Throwable $e) {}

            $table->dropColumn([
                'mismatched_foot',
                'mismatched_actual_size',
                'mismatched_expected_size',
                'product_size',
            ]);
        });

        // Recria o index sem os campos dropados
        Schema::table('damaged_products', function (Blueprint $table) {
            $table->index(['is_mismatched', 'is_damaged']);
        });
    }

    public function down(): void
    {
        Schema::table('damaged_products', function (Blueprint $table) {
            $table->enum('mismatched_foot', ['left', 'right'])->nullable();
            $table->string('mismatched_actual_size', 20)->nullable();
            $table->string('mismatched_expected_size', 20)->nullable();
            $table->string('product_size', 20)->nullable();
        });

        Schema::table('damaged_products', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'mismatched_left_size', 'mismatched_right_size']);
        });
    }
};
