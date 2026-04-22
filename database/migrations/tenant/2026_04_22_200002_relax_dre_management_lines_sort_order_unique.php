<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #2.
 *
 * Remove o UNIQUE sobre `sort_order` em `dre_management_lines`.
 *
 * Motivo: a especificação do prompt #2 exige que a ordem 13 tenha DUAS
 * linhas — "(-) Headcount" (analítica) e "(=) EBITDA" (subtotal),
 * compartilhando o mesmo sort_order. O DreSubtotalCalculator filtra
 * por `is_subtotal` para resolver a ambiguidade no momento de agregar.
 *
 * Ordem de apresentação com empate: subtotais aparecem depois das
 * analíticas do mesmo sort_order. A UI ordena por `(sort_order, is_subtotal)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dre_management_lines', function (Blueprint $table) {
            $table->dropUnique('dre_management_lines_sort_order_unique');
            // Troca por um índice não-único para preservar performance
            // de listagem ordenada.
            $table->index('sort_order', 'dre_management_lines_sort_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dre_management_lines', function (Blueprint $table) {
            $table->dropIndex('dre_management_lines_sort_order_idx');
            $table->unique('sort_order', 'dre_management_lines_sort_order_unique');
        });
    }
};
