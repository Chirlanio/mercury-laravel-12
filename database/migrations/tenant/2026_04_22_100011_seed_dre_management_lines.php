<?php

use App\Models\DreManagementLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DRE — Prompt #1.
 *
 * Seed inicial das linhas da DRE gerencial: 16 linhas DRE-BR padrão
 * derivadas do enum `App\Enums\DreGroup` (11 grupos não-subtotal + 5
 * subtotais calculados: Receita Líquida, Lucro Bruto, Lucro Operacional
 * EBIT, Resultado Antes Impostos, Lucro Líquido) + 1 linha-fantasma
 * `L99_UNCLASSIFIED` para lançamentos sem mapping vigente.
 *
 * Estrutura executiva adicional (Headcount, Marketing e Corporativo,
 * EBITDA formal, Lucro Líquido s/ Cedro) — opção C da §1 do plano
 * arquitetural — será inserida pelo CFO via UI após o prompt #4.
 *
 * Idempotência: se a tabela já tem as linhas, skip silencioso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('dre_management_lines')->where('code', 'L01')->exists()) {
            return;
        }

        $now = now();

        $lines = [
            // --------- Faturamento ---------
            ['code' => 'L01', 'sort' => 10, 'subtotal' => false, 'accum' => null, 'label' => '(+) Receita Bruta', 'nature' => DreManagementLine::NATURE_REVENUE],
            ['code' => 'L02', 'sort' => 20, 'subtotal' => false, 'accum' => null, 'label' => '(-) Deduções da Receita', 'nature' => DreManagementLine::NATURE_EXPENSE],
            ['code' => 'L03', 'sort' => 30, 'subtotal' => true,  'accum' => 20,   'label' => '(=) Receita Líquida', 'nature' => DreManagementLine::NATURE_SUBTOTAL],

            // --------- CMV / Lucro Bruto ---------
            ['code' => 'L04', 'sort' => 40, 'subtotal' => false, 'accum' => null, 'label' => '(-) Custo das Mercadorias / Serviços', 'nature' => DreManagementLine::NATURE_EXPENSE],
            ['code' => 'L05', 'sort' => 50, 'subtotal' => true,  'accum' => 40,   'label' => '(=) Lucro Bruto', 'nature' => DreManagementLine::NATURE_SUBTOTAL],

            // --------- Operacional ---------
            ['code' => 'L06', 'sort' => 60, 'subtotal' => false, 'accum' => null, 'label' => '(-) Despesas Comerciais', 'nature' => DreManagementLine::NATURE_EXPENSE],
            ['code' => 'L07', 'sort' => 70, 'subtotal' => false, 'accum' => null, 'label' => '(-) Despesas Administrativas', 'nature' => DreManagementLine::NATURE_EXPENSE],
            ['code' => 'L08', 'sort' => 80, 'subtotal' => false, 'accum' => null, 'label' => '(-) Despesas Gerais', 'nature' => DreManagementLine::NATURE_EXPENSE],
            ['code' => 'L09', 'sort' => 90, 'subtotal' => false, 'accum' => null, 'label' => '(+) Outras Receitas Operacionais', 'nature' => DreManagementLine::NATURE_REVENUE],
            ['code' => 'L10', 'sort' => 100, 'subtotal' => false, 'accum' => null, 'label' => '(-) Outras Despesas Operacionais', 'nature' => DreManagementLine::NATURE_EXPENSE],
            ['code' => 'L11', 'sort' => 110, 'subtotal' => true,  'accum' => 100,  'label' => '(=) Lucro Operacional (EBIT)', 'nature' => DreManagementLine::NATURE_SUBTOTAL],

            // --------- Financeiro ---------
            ['code' => 'L12', 'sort' => 120, 'subtotal' => false, 'accum' => null, 'label' => '(+) Receitas Financeiras', 'nature' => DreManagementLine::NATURE_REVENUE],
            ['code' => 'L13', 'sort' => 130, 'subtotal' => false, 'accum' => null, 'label' => '(-) Despesas Financeiras', 'nature' => DreManagementLine::NATURE_EXPENSE],
            ['code' => 'L14', 'sort' => 140, 'subtotal' => true,  'accum' => 130,  'label' => '(=) Resultado Antes dos Impostos', 'nature' => DreManagementLine::NATURE_SUBTOTAL],

            // --------- Impostos / Lucro Líquido ---------
            ['code' => 'L15', 'sort' => 150, 'subtotal' => false, 'accum' => null, 'label' => '(-) Impostos sobre o Lucro', 'nature' => DreManagementLine::NATURE_EXPENSE],
            ['code' => 'L16', 'sort' => 160, 'subtotal' => true,  'accum' => 150,  'label' => '(=) Lucro Líquido', 'nature' => DreManagementLine::NATURE_SUBTOTAL],

            // --------- Linha-fantasma ---------
            ['code' => DreManagementLine::UNCLASSIFIED_CODE, 'sort' => 9990, 'subtotal' => false, 'accum' => null, 'label' => '(!) Não classificado', 'nature' => DreManagementLine::NATURE_EXPENSE],
        ];

        foreach ($lines as $line) {
            DB::table('dre_management_lines')->insert([
                'code' => $line['code'],
                'sort_order' => $line['sort'],
                'is_subtotal' => $line['subtotal'],
                'accumulate_until_sort_order' => $line['accum'],
                'level_1' => $line['label'],
                'level_2' => null,
                'level_3' => null,
                'level_4' => null,
                'nature' => $line['nature'],
                'is_active' => true,
                'notes' => null,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dre_management_lines')->truncate();
    }
};
