<?php

use App\Models\DreManagementLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DRE — destravador pré-prompt 6.
 *
 * Semeia a linha-fantasma `L99_UNCLASSIFIED` em `dre_management_lines`.
 * O `DreMappingResolver` (prompt 6) usa essa linha como fallback quando
 * uma combinação (conta analítica, CC) não tem mapping vigente — evita
 * que lançamentos sumam silenciosamente, render a linha em vermelho na
 * matriz para o CFO saber que há algo a classificar.
 *
 * A linha estava no seed original do prompt #1 interno e foi removida
 * no prompt #2 (que entregou as 20 linhas executivas reais do Grupo
 * Meia Sola). Agora voltamos a incluir — sem re-executar o seed
 * executivo.
 *
 * `sort_order=9990` mantém a linha no final da DRE, separada das 20
 * linhas numeradas (sort_order 1..19).
 *
 * Idempotente: se L99 já existir (por exemplo, em ambiente que rodou
 * seed antigo), no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DreManagementLine::where('code', DreManagementLine::UNCLASSIFIED_CODE)->exists()) {
            return;
        }

        $now = now();

        DB::table('dre_management_lines')->insert([
            'code' => DreManagementLine::UNCLASSIFIED_CODE,
            'sort_order' => 9990,
            'is_subtotal' => false,
            'accumulate_until_sort_order' => null,
            'level_1' => '(!) Não classificado',
            'level_2' => null,
            'level_3' => null,
            'level_4' => null,
            'nature' => DreManagementLine::NATURE_EXPENSE,
            'is_active' => true,
            'notes' => 'Linha-fantasma — recebe lançamentos cujo (conta, CC) não tem '
                .'mapping vigente. Zerar significa DRE 100% mapeada.',
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('dre_management_lines')
            ->where('code', DreManagementLine::UNCLASSIFIED_CODE)
            ->delete();
    }
};
