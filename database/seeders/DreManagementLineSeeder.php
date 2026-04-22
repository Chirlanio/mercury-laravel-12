<?php

namespace Database\Seeders;

use App\Models\DreManagementLine;
use Illuminate\Database\Seeder;

/**
 * Semeia as 20 linhas da DRE gerencial executiva do Grupo Meia Sola
 * (estrutura aprovada pelo CFO em 2026-04-21).
 *
 * Atenção especial: a ordem 13 tem DUAS linhas compartilhando o mesmo
 * `sort_order` — "(-) Headcount" (analítica) e "(=) EBITDA" (subtotal).
 * Isto é intencional e respeita a apresentação do Power BI original.
 * O `DreSubtotalCalculator` (próximo prompt) filtra por `is_subtotal`
 * para resolver a ambiguidade na hora de agregar:
 *
 *   - Linhas analíticas (is_subtotal=false) entram no cálculo do
 *     subtotal que acumula até o `sort_order` delas.
 *   - Linhas subtotal (is_subtotal=true) NÃO entram no cálculo de
 *     outros subtotais — são agregações derivadas.
 *
 * `accumulate_until_sort_order` define até qual ordem o subtotal
 * acumula, inclusive. Segue o DAX original do Power BI:
 * `FILTER(ALL(D_Contabil), D_Contabil[Ordem] <= ordem_atual)`.
 *
 * Idempotência: se as 20 linhas já existem (identificadas pelos codes
 * L01..L20), o seeder não duplica. Útil para rodar `db:seed` em
 * ambientes que já passaram por migrate.
 */
class DreManagementLineSeeder extends Seeder
{
    public function run(): void
    {
        if (DreManagementLine::where('code', 'L01')->exists()) {
            return;
        }

        $now = now();

        $lines = [
            // Ordem 1-2: entram em Faturamento Líquido
            ['code' => 'L01', 'sort_order' => 1,  'is_subtotal' => false, 'accum' => null, 'level_1' => '(+) Faturamento Bruto',        'nature' => 'revenue'],
            ['code' => 'L02', 'sort_order' => 2,  'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Deduções da Receita',      'nature' => 'expense'],
            ['code' => 'L03', 'sort_order' => 3,  'is_subtotal' => true,  'accum' => 2,    'level_1' => '(=) Faturamento Líquido',      'nature' => 'subtotal'],

            // Ordem 4: Tributos → ROL
            ['code' => 'L04', 'sort_order' => 4,  'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Tributos sobre venda',     'nature' => 'expense'],
            ['code' => 'L05', 'sort_order' => 5,  'is_subtotal' => true,  'accum' => 4,    'level_1' => '(=) Receita Líquida de Vendas','nature' => 'subtotal'],

            // Ordem 6: CMV → Lucro Bruto
            ['code' => 'L06', 'sort_order' => 6,  'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Custo de Mercadoria',      'nature' => 'expense'],
            ['code' => 'L07', 'sort_order' => 7,  'is_subtotal' => true,  'accum' => 6,    'level_1' => '(=) Lucro Bruto',              'nature' => 'subtotal'],

            // Ordem 8: Custos indiretos → MC
            ['code' => 'L08', 'sort_order' => 8,  'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Custos Indiretos',         'nature' => 'expense'],
            ['code' => 'L09', 'sort_order' => 9,  'is_subtotal' => true,  'accum' => 8,    'level_1' => '(=) Margem de Contribuição',   'nature' => 'subtotal'],

            // Ordens 10-13 analíticas → EBITDA (empate no 13 com Headcount)
            ['code' => 'L10', 'sort_order' => 10, 'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Despesas Gerais (SG&A)',   'nature' => 'expense'],
            ['code' => 'L11', 'sort_order' => 11, 'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Custo de Ocupação',        'nature' => 'expense'],
            ['code' => 'L12', 'sort_order' => 12, 'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Marketing e Corporativo',  'nature' => 'expense'],
            ['code' => 'L13', 'sort_order' => 13, 'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Headcount',                'nature' => 'expense'],
            ['code' => 'L14', 'sort_order' => 13, 'is_subtotal' => true,  'accum' => 13,   'level_1' => '(=) EBITDA',                   'nature' => 'subtotal'],

            // Ordens 14-16 → Lucro Líquido
            ['code' => 'L15', 'sort_order' => 14, 'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Depreciação',              'nature' => 'expense'],
            ['code' => 'L16', 'sort_order' => 15, 'is_subtotal' => false, 'accum' => null, 'level_1' => '(-) Despesas Financeiras',     'nature' => 'expense'],
            ['code' => 'L17', 'sort_order' => 16, 'is_subtotal' => false, 'accum' => null, 'level_1' => '(+) Outras Receitas',          'nature' => 'revenue'],
            ['code' => 'L18', 'sort_order' => 17, 'is_subtotal' => true,  'accum' => 16,   'level_1' => '(=) Lucro Líquido',            'nature' => 'subtotal'],

            // Ordens 18-19 → LL s/ Cedro
            ['code' => 'L19', 'sort_order' => 18, 'is_subtotal' => false, 'accum' => null, 'level_1' => '(=) Ajuste Cedro',             'nature' => 'expense'],
            ['code' => 'L20', 'sort_order' => 19, 'is_subtotal' => true,  'accum' => 18,   'level_1' => '(=) Lucro Líquido s/ Cedro',   'nature' => 'subtotal'],
        ];

        foreach ($lines as $line) {
            DreManagementLine::create([
                'code' => $line['code'],
                'sort_order' => $line['sort_order'],
                'is_subtotal' => $line['is_subtotal'],
                'accumulate_until_sort_order' => $line['accum'],
                'level_1' => $line['level_1'],
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
}
