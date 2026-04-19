<?php

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed do Plano de Contas BR simplificado (~50 contas).
 *
 * Estrutura baseada em CPC/Pronunciamentos Contábeis, simplificada para
 * operação comercial (varejo). Tenant pode personalizar depois via UI
 * ou import.
 *
 * Numeração hierárquica: 3.x = Receitas, 4.x = Custos, 5.x = Despesas,
 * 6.x = Receitas/Despesas Financeiras, 7.x = Impostos.
 *
 * Idempotente: skipa contas que já existem pelo code.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Só roda se a tabela está vazia (evita duplicar em tenants reprocessados)
        if (DB::table('accounting_classes')->exists()) {
            return;
        }

        $now = now();
        $C = AccountingNature::CREDIT->value;
        $D = AccountingNature::DEBIT->value;

        // Mapa code → id será preenchido ao inserir, para resolver parent_id
        // depois (inserts precisam ser em ordem topológica).
        $ids = [];

        $insert = function (array $row) use (&$ids, $now) {
            $row = array_merge([
                'parent_id' => null,
                'description' => null,
                'sort_order' => 0,
                'accepts_entries' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $row);

            if (! empty($row['parent_code'])) {
                $row['parent_id'] = $ids[$row['parent_code']] ?? null;
            }
            unset($row['parent_code']);

            $id = DB::table('accounting_classes')->insertGetId($row);
            $ids[$row['code']] = $id;
        };

        // ==========================================================
        // 3.1 RECEITA BRUTA
        // ==========================================================
        $insert([
            'code' => '3.1', 'name' => 'Receita Bruta',
            'nature' => $C, 'dre_group' => DreGroup::RECEITA_BRUTA->value,
            'accepts_entries' => false, 'sort_order' => 10,
            'description' => 'Grupo sintético — totaliza vendas brutas de mercadorias e serviços.',
        ]);
        $insert([
            'code' => '3.1.01', 'name' => 'Vendas de Mercadorias', 'parent_code' => '3.1',
            'nature' => $C, 'dre_group' => DreGroup::RECEITA_BRUTA->value,
            'accepts_entries' => false, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '3.1.01.001', 'name' => 'Vendas Loja Física', 'parent_code' => '3.1.01',
            'nature' => $C, 'dre_group' => DreGroup::RECEITA_BRUTA->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '3.1.01.002', 'name' => 'Vendas E-commerce', 'parent_code' => '3.1.01',
            'nature' => $C, 'dre_group' => DreGroup::RECEITA_BRUTA->value, 'sort_order' => 20,
        ]);
        $insert([
            'code' => '3.1.02', 'name' => 'Prestação de Serviços', 'parent_code' => '3.1',
            'nature' => $C, 'dre_group' => DreGroup::RECEITA_BRUTA->value, 'sort_order' => 20,
        ]);

        // ==========================================================
        // 3.2 DEDUÇÕES DA RECEITA
        // ==========================================================
        $insert([
            'code' => '3.2', 'name' => 'Deduções da Receita',
            'nature' => $D, 'dre_group' => DreGroup::DEDUCOES->value,
            'accepts_entries' => false, 'sort_order' => 20,
            'description' => 'Grupo sintético — impostos sobre vendas e devoluções.',
        ]);
        $insert([
            'code' => '3.2.01', 'name' => 'Impostos sobre Vendas', 'parent_code' => '3.2',
            'nature' => $D, 'dre_group' => DreGroup::DEDUCOES->value,
            'accepts_entries' => false, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '3.2.01.001', 'name' => 'ICMS', 'parent_code' => '3.2.01',
            'nature' => $D, 'dre_group' => DreGroup::DEDUCOES->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '3.2.01.002', 'name' => 'PIS', 'parent_code' => '3.2.01',
            'nature' => $D, 'dre_group' => DreGroup::DEDUCOES->value, 'sort_order' => 20,
        ]);
        $insert([
            'code' => '3.2.01.003', 'name' => 'COFINS', 'parent_code' => '3.2.01',
            'nature' => $D, 'dre_group' => DreGroup::DEDUCOES->value, 'sort_order' => 30,
        ]);
        $insert([
            'code' => '3.2.01.004', 'name' => 'ISS', 'parent_code' => '3.2.01',
            'nature' => $D, 'dre_group' => DreGroup::DEDUCOES->value, 'sort_order' => 40,
        ]);
        $insert([
            'code' => '3.2.02', 'name' => 'Devoluções de Vendas', 'parent_code' => '3.2',
            'nature' => $D, 'dre_group' => DreGroup::DEDUCOES->value, 'sort_order' => 20,
        ]);
        $insert([
            'code' => '3.2.03', 'name' => 'Descontos Incondicionais Concedidos', 'parent_code' => '3.2',
            'nature' => $D, 'dre_group' => DreGroup::DEDUCOES->value, 'sort_order' => 30,
        ]);

        // ==========================================================
        // 4 CUSTO DAS MERCADORIAS VENDIDAS (CMV)
        // ==========================================================
        $insert([
            'code' => '4.1', 'name' => 'Custo das Mercadorias Vendidas',
            'nature' => $D, 'dre_group' => DreGroup::CMV->value,
            'accepts_entries' => false, 'sort_order' => 30,
        ]);
        $insert([
            'code' => '4.1.01', 'name' => 'Custo dos Produtos Vendidos', 'parent_code' => '4.1',
            'nature' => $D, 'dre_group' => DreGroup::CMV->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '4.1.02', 'name' => 'Custo de Serviços Prestados', 'parent_code' => '4.1',
            'nature' => $D, 'dre_group' => DreGroup::CMV->value, 'sort_order' => 20,
        ]);

        // ==========================================================
        // 5.1 DESPESAS COMERCIAIS
        // ==========================================================
        $insert([
            'code' => '5.1', 'name' => 'Despesas Comerciais',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_COMERCIAIS->value,
            'accepts_entries' => false, 'sort_order' => 40,
        ]);
        $insert([
            'code' => '5.1.01', 'name' => 'Marketing e Publicidade', 'parent_code' => '5.1',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_COMERCIAIS->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '5.1.02', 'name' => 'Frete de Vendas', 'parent_code' => '5.1',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_COMERCIAIS->value, 'sort_order' => 20,
        ]);
        $insert([
            'code' => '5.1.03', 'name' => 'Comissões de Vendas', 'parent_code' => '5.1',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_COMERCIAIS->value, 'sort_order' => 30,
        ]);
        $insert([
            'code' => '5.1.04', 'name' => 'Embalagens', 'parent_code' => '5.1',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_COMERCIAIS->value, 'sort_order' => 40,
        ]);

        // ==========================================================
        // 5.2 DESPESAS ADMINISTRATIVAS
        // ==========================================================
        $insert([
            'code' => '5.2', 'name' => 'Despesas Administrativas',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value,
            'accepts_entries' => false, 'sort_order' => 50,
        ]);
        $insert([
            'code' => '5.2.01', 'name' => 'Salários e Ordenados', 'parent_code' => '5.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '5.2.02', 'name' => 'Encargos Sociais (INSS/FGTS)', 'parent_code' => '5.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sort_order' => 20,
        ]);
        $insert([
            'code' => '5.2.03', 'name' => 'Aluguel', 'parent_code' => '5.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sort_order' => 30,
        ]);
        $insert([
            'code' => '5.2.04', 'name' => 'Energia Elétrica e Água', 'parent_code' => '5.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sort_order' => 40,
        ]);
        $insert([
            'code' => '5.2.05', 'name' => 'Telefonia e Internet', 'parent_code' => '5.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sort_order' => 50,
        ]);
        $insert([
            'code' => '5.2.06', 'name' => 'Material de Escritório', 'parent_code' => '5.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sort_order' => 60,
        ]);
        $insert([
            'code' => '5.2.07', 'name' => 'Serviços Contratados', 'parent_code' => '5.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sort_order' => 70,
        ]);
        $insert([
            'code' => '5.2.08', 'name' => 'Honorários Contábeis/Jurídicos', 'parent_code' => '5.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sort_order' => 80,
        ]);

        // ==========================================================
        // 5.3 DESPESAS GERAIS
        // ==========================================================
        $insert([
            'code' => '5.3', 'name' => 'Despesas Gerais',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_GERAIS->value,
            'accepts_entries' => false, 'sort_order' => 60,
        ]);
        $insert([
            'code' => '5.3.01', 'name' => 'Depreciação', 'parent_code' => '5.3',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_GERAIS->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '5.3.02', 'name' => 'Amortização', 'parent_code' => '5.3',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_GERAIS->value, 'sort_order' => 20,
        ]);
        $insert([
            'code' => '5.3.03', 'name' => 'Seguros', 'parent_code' => '5.3',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_GERAIS->value, 'sort_order' => 30,
        ]);
        $insert([
            'code' => '5.3.04', 'name' => 'Manutenção e Reparos', 'parent_code' => '5.3',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_GERAIS->value, 'sort_order' => 40,
        ]);

        // ==========================================================
        // 5.4 OUTRAS RECEITAS OPERACIONAIS
        // ==========================================================
        $insert([
            'code' => '5.4', 'name' => 'Outras Receitas Operacionais',
            'nature' => $C, 'dre_group' => DreGroup::OUTRAS_RECEITAS_OP->value,
            'accepts_entries' => false, 'sort_order' => 70,
        ]);
        $insert([
            'code' => '5.4.01', 'name' => 'Venda de Sucata/Material Obsoleto', 'parent_code' => '5.4',
            'nature' => $C, 'dre_group' => DreGroup::OUTRAS_RECEITAS_OP->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '5.4.02', 'name' => 'Recuperação de Despesas', 'parent_code' => '5.4',
            'nature' => $C, 'dre_group' => DreGroup::OUTRAS_RECEITAS_OP->value, 'sort_order' => 20,
        ]);

        // ==========================================================
        // 5.5 OUTRAS DESPESAS OPERACIONAIS
        // ==========================================================
        $insert([
            'code' => '5.5', 'name' => 'Outras Despesas Operacionais',
            'nature' => $D, 'dre_group' => DreGroup::OUTRAS_DESPESAS_OP->value,
            'accepts_entries' => false, 'sort_order' => 80,
        ]);
        $insert([
            'code' => '5.5.01', 'name' => 'Perdas com Inadimplência', 'parent_code' => '5.5',
            'nature' => $D, 'dre_group' => DreGroup::OUTRAS_DESPESAS_OP->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '5.5.02', 'name' => 'Multas e Penalidades', 'parent_code' => '5.5',
            'nature' => $D, 'dre_group' => DreGroup::OUTRAS_DESPESAS_OP->value, 'sort_order' => 20,
        ]);

        // ==========================================================
        // 6.1 RECEITAS FINANCEIRAS
        // ==========================================================
        $insert([
            'code' => '6.1', 'name' => 'Receitas Financeiras',
            'nature' => $C, 'dre_group' => DreGroup::RECEITAS_FINANCEIRAS->value,
            'accepts_entries' => false, 'sort_order' => 90,
        ]);
        $insert([
            'code' => '6.1.01', 'name' => 'Rendimentos de Aplicações Financeiras', 'parent_code' => '6.1',
            'nature' => $C, 'dre_group' => DreGroup::RECEITAS_FINANCEIRAS->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '6.1.02', 'name' => 'Descontos Obtidos', 'parent_code' => '6.1',
            'nature' => $C, 'dre_group' => DreGroup::RECEITAS_FINANCEIRAS->value, 'sort_order' => 20,
        ]);
        $insert([
            'code' => '6.1.03', 'name' => 'Juros Ativos', 'parent_code' => '6.1',
            'nature' => $C, 'dre_group' => DreGroup::RECEITAS_FINANCEIRAS->value, 'sort_order' => 30,
        ]);

        // ==========================================================
        // 6.2 DESPESAS FINANCEIRAS
        // ==========================================================
        $insert([
            'code' => '6.2', 'name' => 'Despesas Financeiras',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_FINANCEIRAS->value,
            'accepts_entries' => false, 'sort_order' => 100,
        ]);
        $insert([
            'code' => '6.2.01', 'name' => 'Juros Passivos', 'parent_code' => '6.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_FINANCEIRAS->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '6.2.02', 'name' => 'Tarifas Bancárias', 'parent_code' => '6.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_FINANCEIRAS->value, 'sort_order' => 20,
        ]);
        $insert([
            'code' => '6.2.03', 'name' => 'Descontos Concedidos', 'parent_code' => '6.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_FINANCEIRAS->value, 'sort_order' => 30,
        ]);
        $insert([
            'code' => '6.2.04', 'name' => 'IOF', 'parent_code' => '6.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_FINANCEIRAS->value, 'sort_order' => 40,
        ]);
        $insert([
            'code' => '6.2.05', 'name' => 'Taxas de Cartão (Adquirente)', 'parent_code' => '6.2',
            'nature' => $D, 'dre_group' => DreGroup::DESPESAS_FINANCEIRAS->value, 'sort_order' => 50,
        ]);

        // ==========================================================
        // 7.1 IMPOSTOS SOBRE O LUCRO
        // ==========================================================
        $insert([
            'code' => '7.1', 'name' => 'Impostos sobre o Lucro',
            'nature' => $D, 'dre_group' => DreGroup::IMPOSTOS_SOBRE_LUCRO->value,
            'accepts_entries' => false, 'sort_order' => 110,
        ]);
        $insert([
            'code' => '7.1.01', 'name' => 'IRPJ (Imposto de Renda Pessoa Jurídica)', 'parent_code' => '7.1',
            'nature' => $D, 'dre_group' => DreGroup::IMPOSTOS_SOBRE_LUCRO->value, 'sort_order' => 10,
        ]);
        $insert([
            'code' => '7.1.02', 'name' => 'CSLL (Contribuição Social sobre Lucro Líquido)', 'parent_code' => '7.1',
            'nature' => $D, 'dre_group' => DreGroup::IMPOSTOS_SOBRE_LUCRO->value, 'sort_order' => 20,
        ]);
    }

    public function down(): void
    {
        DB::table('accounting_classes')->truncate();
    }
};
