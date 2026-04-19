<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reseed do Plano de Contas Contábil com os 80 códigos reais extraídos do
 * razão gerencial 05/2023 do Grupo Meia Sola.
 *
 * Substitui o seed genérico BR (Fase 0.2) pela estrutura em uso na
 * contabilidade da empresa. Mantém o mesmo schema — apenas troca os dados.
 *
 * Comportamento:
 *   1. Se existe a conta '3.1.1.01.00012' (marca do seed real) → skipa.
 *      Significa que a migration já rodou.
 *   2. Se a tabela só contém os códigos do seed genérico anterior → remove
 *      todos e insere os reais.
 *   3. Se existem códigos fora do seed genérico (customizações do tenant)
 *      → aborta sem alterar. Não destrói trabalho manual.
 *   4. Se a tabela está vazia → insere os reais direto.
 *
 * Estrutura: 21 grupos sintéticos (3, 3.1, 3.1.1, 3.1.1.01, 3.1.1.02, 3.1.2,
 * 3.2, 3.2.1, 3.2.1.01, 3.2.1.02, 4, 4.1, 4.1.1, 4.2, 4.2.1, 4.2.1.01..06)
 * + 80 contas analíticas no último nível.
 *
 * Codigos analiticos seguem o formato X.X.X.XX.XXXXX usado pela contabilidade
 * externa (ex: 3.1.1.01.00012 = "Receita de Vendas").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_classes')) {
            return;
        }

        // 1. Já rodou?
        if (DB::table('accounting_classes')->where('code', '3.1.1.01.00012')->exists()) {
            return;
        }

        $oldSeedCodes = [
            '3.1',
            '3.1.01',
            '3.1.01.001',
            '3.1.01.002',
            '3.1.02',
            '3.2',
            '3.2.01',
            '3.2.01.001',
            '3.2.01.002',
            '3.2.01.003',
            '3.2.01.004',
            '3.2.02',
            '3.2.03',
            '4.1',
            '4.1.01',
            '4.1.02',
            '5.1',
            '5.1.01',
            '5.1.02',
            '5.1.03',
            '5.1.04',
            '5.2',
            '5.2.01',
            '5.2.02',
            '5.2.03',
            '5.2.04',
            '5.2.05',
            '5.2.06',
            '5.2.07',
            '5.2.08',
            '5.3',
            '5.3.01',
            '5.3.02',
            '5.3.03',
            '5.3.04',
            '5.4',
            '5.4.01',
            '5.4.02',
            '5.5',
            '5.5.01',
            '5.5.02',
            '6.1',
            '6.1.01',
            '6.1.02',
            '6.1.03',
            '6.2',
            '6.2.01',
            '6.2.02',
            '6.2.03',
            '6.2.04',
            '6.2.05',
            '7.1',
            '7.1.01',
            '7.1.02',
        ];

        $total = DB::table('accounting_classes')->count();
        $oldCount = DB::table('accounting_classes')->whereIn('code', $oldSeedCodes)->count();

        if ($total > 0 && $oldCount < $total) {
            // Há customizações — não mexer. Tenant deve reconciliar manualmente.
            throw new \RuntimeException(
                'accounting_classes contém códigos fora do seed genérico; ' .
                'reseed abortado para não destruir customizações. Reconcilie ' .
                'manualmente antes de rodar esta migration.'
            );
        }

        // Remove o seed genérico antigo (se existir)
        if ($oldCount > 0) {
            DB::table('accounting_classes')->whereIn('code', $oldSeedCodes)->delete();
        }

        $now = now();
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

        // Sintéticos (grupos agregadores) — ordem topológica garantida pelo sort_order
        $synthetics = [
                ['code' => '3', 'name' => 'Receitas', 'parent_code' => NULL, 'dre_group' => 'receita_bruta', 'nature' => 'credit', 'sort_order' => 10],
                ['code' => '3.1', 'name' => 'Receitas Operacionais', 'parent_code' => '3', 'dre_group' => 'receita_bruta', 'nature' => 'credit', 'sort_order' => 20],
                ['code' => '3.1.1', 'name' => 'Receitas de Vendas', 'parent_code' => '3.1', 'dre_group' => 'receita_bruta', 'nature' => 'credit', 'sort_order' => 30],
                ['code' => '3.1.1.01', 'name' => 'Receita Operacional Bruta', 'parent_code' => '3.1.1', 'dre_group' => 'receita_bruta', 'nature' => 'credit', 'sort_order' => 40],
                ['code' => '3.1.1.02', 'name' => 'Deduções da Receita', 'parent_code' => '3.1.1', 'dre_group' => 'deducoes', 'nature' => 'debit', 'sort_order' => 50],
                ['code' => '3.1.2', 'name' => 'Receitas Financeiras', 'parent_code' => '3.1', 'dre_group' => 'receitas_financeiras', 'nature' => 'credit', 'sort_order' => 60],
                ['code' => '3.2', 'name' => 'Receitas Não Operacionais', 'parent_code' => '3', 'dre_group' => 'outras_receitas_op', 'nature' => 'credit', 'sort_order' => 70],
                ['code' => '3.2.1', 'name' => 'Outras Receitas', 'parent_code' => '3.2', 'dre_group' => 'outras_receitas_op', 'nature' => 'credit', 'sort_order' => 80],
                ['code' => '3.2.1.01', 'name' => 'Outras Receitas Operacionais', 'parent_code' => '3.2.1', 'dre_group' => 'outras_receitas_op', 'nature' => 'credit', 'sort_order' => 90],
                ['code' => '3.2.1.02', 'name' => 'Deduções de Outras Receitas', 'parent_code' => '3.2.1', 'dre_group' => 'deducoes', 'nature' => 'debit', 'sort_order' => 100],
                ['code' => '4', 'name' => 'Custos e Despesas', 'parent_code' => NULL, 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 110],
                ['code' => '4.1', 'name' => 'Custos Operacionais', 'parent_code' => '4', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 120],
                ['code' => '4.1.1', 'name' => 'Custo das Mercadorias Vendidas', 'parent_code' => '4.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 130],
                ['code' => '4.2', 'name' => 'Despesas Operacionais', 'parent_code' => '4', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 140],
                ['code' => '4.2.1', 'name' => 'Despesas Operacionais (Detalhe)', 'parent_code' => '4.2', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 150],
                ['code' => '4.2.1.01', 'name' => 'Despesas Comerciais', 'parent_code' => '4.2.1', 'dre_group' => 'despesas_comerciais', 'nature' => 'debit', 'sort_order' => 160],
                ['code' => '4.2.1.02', 'name' => 'Despesas com Pessoal', 'parent_code' => '4.2.1', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 170],
                ['code' => '4.2.1.03', 'name' => 'Impostos e Taxas', 'parent_code' => '4.2.1', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 180],
                ['code' => '4.2.1.04', 'name' => 'Despesas Administrativas', 'parent_code' => '4.2.1', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 190],
                ['code' => '4.2.1.05', 'name' => 'Despesas Financeiras', 'parent_code' => '4.2.1', 'dre_group' => 'despesas_financeiras', 'nature' => 'debit', 'sort_order' => 200],
                ['code' => '4.2.1.06', 'name' => 'Despesas Não Dedutíveis', 'parent_code' => '4.2.1', 'dre_group' => 'outras_despesas_op', 'nature' => 'debit', 'sort_order' => 210],
            ];

        foreach ($synthetics as $s) {
            $insert($s + ['accepts_entries' => false]);
        }

        // Analíticas (folhas) — 80 contas reais da planilha
        $analyticals = [
                ['code' => '3.1.1.01.00012', 'name' => 'Receita de Vendas', 'description' => 'RECEITA DE VENDAS', 'parent_code' => '3.1.1.01', 'dre_group' => 'receita_bruta', 'nature' => 'credit', 'sort_order' => 310],
                ['code' => '3.1.1.02.00017', 'name' => 'Vendas Canceladas ou Devolvidas', 'description' => 'VENDAS CANCELADAS OU DEVOLVIDAS', 'parent_code' => '3.1.1.02', 'dre_group' => 'deducoes', 'nature' => 'debit', 'sort_order' => 320],
                ['code' => '3.1.1.02.00025', 'name' => 'ICMS S/ Vendas', 'description' => 'ICMS S/ VENDAS', 'parent_code' => '3.1.1.02', 'dre_group' => 'deducoes', 'nature' => 'debit', 'sort_order' => 330],
                ['code' => '3.1.1.02.00033', 'name' => 'PIS S/ Vendas', 'description' => 'PIS S/ VENDAS', 'parent_code' => '3.1.1.02', 'dre_group' => 'deducoes', 'nature' => 'debit', 'sort_order' => 340],
                ['code' => '3.1.1.02.00041', 'name' => 'COFINS S/ Vendas', 'description' => 'COFINS S/ VENDAS', 'parent_code' => '3.1.1.02', 'dre_group' => 'deducoes', 'nature' => 'debit', 'sort_order' => 350],
                ['code' => '3.1.2.01.00015', 'name' => 'Juros de Mora Recebidos', 'description' => 'JUROS DE MORA RECEBIDOS', 'parent_code' => '3.1.2', 'dre_group' => 'receitas_financeiras', 'nature' => 'credit', 'sort_order' => 360],
                ['code' => '3.1.2.01.00023', 'name' => 'Descontos Obtidos', 'description' => 'DESCONTOS OBTIDOS', 'parent_code' => '3.1.2', 'dre_group' => 'receitas_financeiras', 'nature' => 'credit', 'sort_order' => 370],
                ['code' => '3.1.2.01.00040', 'name' => 'Rendimento de Aplicacoes Financeiras', 'description' => 'RENDIMENTO DE APLICACOES FINANCEIRAS', 'parent_code' => '3.1.2', 'dre_group' => 'receitas_financeiras', 'nature' => 'credit', 'sort_order' => 380],
                ['code' => '3.1.2.01.00082', 'name' => 'Receita de Selic S/Creditos Tributarios', 'description' => 'RECEITA DE SELIC S/CREDITOS TRIBUTARIOS', 'parent_code' => '3.1.2', 'dre_group' => 'receitas_financeiras', 'nature' => 'credit', 'sort_order' => 390],
                ['code' => '3.2.1.01.00022', 'name' => 'Outras Receitas', 'description' => 'OUTRAS RECEITAS', 'parent_code' => '3.2.1.01', 'dre_group' => 'outras_receitas_op', 'nature' => 'credit', 'sort_order' => 400],
                ['code' => '3.2.1.01.00030', 'name' => 'Receita de Credito Tributario Proc Judic', 'description' => 'RECEITA DE CREDITO TRIBUTARIO PROC JUDIC', 'parent_code' => '3.2.1.01', 'dre_group' => 'outras_receitas_op', 'nature' => 'credit', 'sort_order' => 410],
                ['code' => '3.2.1.02.00001', 'name' => 'PIS S/Outras Receitas Operacionais', 'description' => 'PIS S/OUTRAS RECEITAS OPERACIONAIS', 'parent_code' => '3.2.1.02', 'dre_group' => 'deducoes', 'nature' => 'debit', 'sort_order' => 420],
                ['code' => '3.2.1.02.00002', 'name' => 'COFINS S/Outras Receitas Operacionais', 'description' => 'COFINS S/OUTRAS RECEITAS OPERACIONAIS', 'parent_code' => '3.2.1.02', 'dre_group' => 'deducoes', 'nature' => 'debit', 'sort_order' => 430],
                ['code' => '4.1.1.01.00010', 'name' => 'Estoque Inicial', 'description' => 'ESTOQUE INICIAL', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 440],
                ['code' => '4.1.1.02.00015', 'name' => 'Compras de Mercadoria P/ Revenda', 'description' => 'COMPRAS DE MERCADORIA P/ REVENDA', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 450],
                ['code' => '4.1.1.02.00031', 'name' => '(-) Devolucao de Compras', 'description' => '(-) DEVOLUCAO DE COMPRAS', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 460],
                ['code' => '4.1.1.02.00040', 'name' => '(-) ICMS S/Compras', 'description' => '(-) ICMS S/COMPRAS', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 470],
                ['code' => '4.1.1.02.00058', 'name' => '(-) PIS S/ Compras', 'description' => '(-) PIS S/ COMPRAS', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 480],
                ['code' => '4.1.1.02.00066', 'name' => '(-) COFINS S/Compras', 'description' => '(-) COFINS S/COMPRAS', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 490],
                ['code' => '4.1.1.02.00074', 'name' => '(+) Transferencia de Filiais', 'description' => '(+) TRANSFERENCIA DE FILIAIS', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 500],
                ['code' => '4.1.1.02.00082', 'name' => '(-) Transferencia P/ Filiais', 'description' => '(-) TRANSFERENCIA P/ FILIAIS', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 510],
                ['code' => '4.1.1.02.00090', 'name' => '(+-) ICMS S/ Transferencia', 'description' => '(+-) ICMS S/ TRANSFERENCIA', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 520],
                ['code' => '4.1.1.02.00104', 'name' => '(+) Outras Entradas', 'description' => '(+) OUTRAS ENTRADAS', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 530],
                ['code' => '4.1.1.02.00112', 'name' => '(-) Outras Saidas', 'description' => '(-) OUTRAS SAIDAS', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 540],
                ['code' => '4.1.1.02.00120', 'name' => '(-+) ICMS S/ Outras Entradas/Outras Said', 'description' => '(-+) ICMS S/ OUTRAS ENTRADAS/OUTRAS SAID', 'parent_code' => '4.1.1', 'dre_group' => 'cmv', 'nature' => 'debit', 'sort_order' => 550],
                ['code' => '4.2.1.01.00020', 'name' => 'Publicidade Propaganda e Material Grafic', 'description' => 'PUBLICIDADE PROPAGANDA E MATERIAL GRAFIC', 'parent_code' => '4.2.1.01', 'dre_group' => 'despesas_comerciais', 'nature' => 'debit', 'sort_order' => 560],
                ['code' => '4.2.1.01.00047', 'name' => 'Eventos', 'description' => 'EVENTOS', 'parent_code' => '4.2.1.01', 'dre_group' => 'despesas_comerciais', 'nature' => 'debit', 'sort_order' => 570],
                ['code' => '4.2.1.01.00055', 'name' => 'Material de Embalagem', 'description' => 'MATERIAL DE EMBALAGEM', 'parent_code' => '4.2.1.01', 'dre_group' => 'despesas_comerciais', 'nature' => 'debit', 'sort_order' => 580],
                ['code' => '4.2.1.01.00080', 'name' => 'Fretes e Carretos', 'description' => 'FRETES E CARRETOS', 'parent_code' => '4.2.1.01', 'dre_group' => 'despesas_comerciais', 'nature' => 'debit', 'sort_order' => 590],
                ['code' => '4.2.1.01.00098', 'name' => 'Processamento de Dados', 'description' => 'PROCESSAMENTO DE DADOS', 'parent_code' => '4.2.1.01', 'dre_group' => 'despesas_comerciais', 'nature' => 'debit', 'sort_order' => 600],
                ['code' => '4.2.1.01.00101', 'name' => 'Brindes e Bonificacoes S/ Vendas', 'description' => 'BRINDES E BONIFICACOES S/ VENDAS', 'parent_code' => '4.2.1.01', 'dre_group' => 'despesas_comerciais', 'nature' => 'debit', 'sort_order' => 610],
                ['code' => '4.2.1.02.00050', 'name' => 'Fgts', 'description' => 'FGTS', 'parent_code' => '4.2.1.02', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 620],
                ['code' => '4.2.1.02.00106', 'name' => 'Fgts Rescisorio', 'description' => 'FGTS RESCISORIO', 'parent_code' => '4.2.1.02', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 630],
                ['code' => '4.2.1.02.00165', 'name' => 'Medicina No Trabalho e Exames', 'description' => 'MEDICINA NO TRABALHO E EXAMES', 'parent_code' => '4.2.1.02', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 640],
                ['code' => '4.2.1.02.00262', 'name' => 'Contribuicao Sindical', 'description' => 'CONTRIBUICAO SINDICAL', 'parent_code' => '4.2.1.02', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 650],
                ['code' => '4.2.1.03.00011', 'name' => 'IPTU', 'description' => 'IPTU', 'parent_code' => '4.2.1.03', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 660],
                ['code' => '4.2.1.03.00020', 'name' => 'ICMS Substituicao Tributaria', 'description' => 'ICMS SUBSTITUICAO TRIBUTARIA', 'parent_code' => '4.2.1.03', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 670],
                ['code' => '4.2.1.03.00038', 'name' => 'ICMS Diferencial de Aliquota', 'description' => 'ICMS DIFERENCIAL DE ALIQUOTA', 'parent_code' => '4.2.1.03', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 680],
                ['code' => '4.2.1.03.00046', 'name' => 'Outros Impostos e Taxas', 'description' => 'OUTROS IMPOSTOS E TAXAS', 'parent_code' => '4.2.1.03', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 690],
                ['code' => '4.2.1.03.00062', 'name' => 'IOF', 'description' => 'IOF', 'parent_code' => '4.2.1.03', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 700],
                ['code' => '4.2.1.03.00070', 'name' => 'IPVA', 'description' => 'IPVA', 'parent_code' => '4.2.1.03', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 710],
                ['code' => '4.2.1.04.00032', 'name' => 'Telefonia', 'description' => 'TELEFONIA', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 720],
                ['code' => '4.2.1.04.00040', 'name' => 'Honorarios Contabeis', 'description' => 'HONORARIOS CONTABEIS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 730],
                ['code' => '4.2.1.04.00075', 'name' => 'Combustivel e Lubrificantes', 'description' => 'COMBUSTIVEL E LUBRIFICANTES', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 740],
                ['code' => '4.2.1.04.00083', 'name' => 'Outras Despesas', 'description' => 'OUTRAS DESPESAS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 750],
                ['code' => '4.2.1.04.00091', 'name' => 'Material e Suprimentos de Informatica', 'description' => 'MATERIAL E SUPRIMENTOS DE INFORMATICA', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 760],
                ['code' => '4.2.1.04.00105', 'name' => 'Internet Softwares e Sistemas', 'description' => 'INTERNET SOFTWARES E SISTEMAS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 770],
                ['code' => '4.2.1.04.00121', 'name' => 'Condominios', 'description' => 'CONDOMINIOS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 780],
                ['code' => '4.2.1.04.00130', 'name' => 'Correios e Telegrafos', 'description' => 'CORREIOS E TELEGRAFOS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 790],
                ['code' => '4.2.1.04.00156', 'name' => 'Despesas com Viagens e Hospedagens', 'description' => 'DESPESAS COM VIAGENS E HOSPEDAGENS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 800],
                ['code' => '4.2.1.04.00164', 'name' => 'Material de Uso e Consumo', 'description' => 'MATERIAL DE USO E CONSUMO', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 810],
                ['code' => '4.2.1.04.00202', 'name' => 'Vigilancia e Seguranca', 'description' => 'VIGILANCIA E SEGURANCA', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 820],
                ['code' => '4.2.1.04.00210', 'name' => 'Honorarios Advocaticios', 'description' => 'HONORARIOS ADVOCATICIOS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 830],
                ['code' => '4.2.1.04.00229', 'name' => 'Seguros', 'description' => 'SEGUROS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 840],
                ['code' => '4.2.1.04.00237', 'name' => 'Agua e Esgotos', 'description' => 'AGUA E ESGOTOS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 850],
                ['code' => '4.2.1.04.00245', 'name' => 'Energia Eletrica', 'description' => 'ENERGIA ELETRICA', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 860],
                ['code' => '4.2.1.04.00253', 'name' => 'Despesa C/ Manutencao', 'description' => 'DESPESA C/ MANUTENCAO', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 870],
                ['code' => '4.2.1.04.00270', 'name' => 'Servicos Prestados', 'description' => 'SERVICOS PRESTADOS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 880],
                ['code' => '4.2.1.04.00296', 'name' => 'Taxas Diversas', 'description' => 'TAXAS DIVERSAS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 890],
                ['code' => '4.2.1.04.00342', 'name' => '(-) Credito de PIS S/Despesas', 'description' => '(-) CREDITO DE PIS S/DESPESAS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 900],
                ['code' => '4.2.1.04.00350', 'name' => '(-) Credito de COFINS S/ Desepesas', 'description' => '(-) CREDITO DE COFINS S/ DESEPESAS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 910],
                ['code' => '4.2.1.04.00377', 'name' => 'Aluguel de Maquinas e Equipamentos', 'description' => 'ALUGUEL DE MAQUINAS E EQUIPAMENTOS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 920],
                ['code' => '4.2.1.04.00385', 'name' => 'Marcas e Patentes', 'description' => 'MARCAS E PATENTES', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 930],
                ['code' => '4.2.1.04.00407', 'name' => 'Fundo de Promocao Aluguel', 'description' => 'FUNDO DE PROMOCAO ALUGUEL', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 940],
                ['code' => '4.2.1.04.00466', 'name' => 'Estacionamento', 'description' => 'ESTACIONAMENTO', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 950],
                ['code' => '4.2.1.04.00482', 'name' => 'Moto Taxi', 'description' => 'MOTO TAXI', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 960],
                ['code' => '4.2.1.04.00512', 'name' => 'Despesa com Dedetizacao', 'description' => 'DESPESA COM DEDETIZACAO', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 970],
                ['code' => '4.2.1.04.00547', 'name' => 'Agua Mineral', 'description' => 'AGUA MINERAL', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 980],
                ['code' => '4.2.1.04.00571', 'name' => 'Alimentacao', 'description' => 'ALIMENTACAO', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 990],
                ['code' => '4.2.1.04.00598', 'name' => 'Alugueis Percentuais', 'description' => 'ALUGUEIS PERCENTUAIS', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 1000],
                ['code' => '4.2.1.04.00601', 'name' => 'Telecheque', 'description' => 'TELECHEQUE', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 1010],
                ['code' => '4.2.1.04.00610', 'name' => 'Guarda Documental', 'description' => 'GUARDA DOCUMENTAL', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 1020],
                ['code' => '4.2.1.04.00636', 'name' => '(-) Recuperacao de Despesa', 'description' => '(-) RECUPERACAO DE DESPESA', 'parent_code' => '4.2.1.04', 'dre_group' => 'despesas_administrativas', 'nature' => 'debit', 'sort_order' => 1030],
                ['code' => '4.2.1.05.00029', 'name' => 'Juros S/ Financiamentos e Emprestimos', 'description' => 'JUROS S/ FINANCIAMENTOS E EMPRESTIMOS', 'parent_code' => '4.2.1.05', 'dre_group' => 'despesas_financeiras', 'nature' => 'debit', 'sort_order' => 1040],
                ['code' => '4.2.1.05.00030', 'name' => 'Juros S/ Parcelamentos', 'description' => 'JUROS S/ PARCELAMENTOS', 'parent_code' => '4.2.1.05', 'dre_group' => 'despesas_financeiras', 'nature' => 'debit', 'sort_order' => 1050],
                ['code' => '4.2.1.05.00053', 'name' => 'Juros Passivo', 'description' => 'JUROS PASSIVO', 'parent_code' => '4.2.1.05', 'dre_group' => 'despesas_financeiras', 'nature' => 'debit', 'sort_order' => 1060],
                ['code' => '4.2.1.05.00096', 'name' => 'Tarifas', 'description' => 'TARIFAS', 'parent_code' => '4.2.1.05', 'dre_group' => 'despesas_financeiras', 'nature' => 'debit', 'sort_order' => 1070],
                ['code' => '4.2.1.05.00118', 'name' => 'Multas', 'description' => 'MULTAS', 'parent_code' => '4.2.1.05', 'dre_group' => 'despesas_financeiras', 'nature' => 'debit', 'sort_order' => 1080],
                ['code' => '4.2.1.06.00015', 'name' => 'Juros e Multas Indedutiveis', 'description' => 'JUROS E MULTAS INDEDUTIVEIS', 'parent_code' => '4.2.1.06', 'dre_group' => 'outras_despesas_op', 'nature' => 'debit', 'sort_order' => 1090],
                ['code' => '4.2.1.06.00074', 'name' => 'Despesas Indedutiveis', 'description' => 'DESPESAS INDEDUTIVEIS', 'parent_code' => '4.2.1.06', 'dre_group' => 'outras_despesas_op', 'nature' => 'debit', 'sort_order' => 1100],
            ];

        foreach ($analyticals as $a) {
            $insert($a);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_classes')) {
            return;
        }
        // Remove as contas novas. Não restaura o seed genérico anterior —
        // rode a migration 2026_04_20_300002_seed_br_accounting_classes.php
        // novamente se precisar do template BR antigo.
        DB::table('accounting_classes')->truncate();
    }
};