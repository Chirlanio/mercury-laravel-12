<?php

/**
 * Script one-shot para gerar `plano-contas-exemplo.xlsx` (fixture de dev).
 *
 * Gera um XLSX reduzido com ~30 linhas cobrindo todos os V_Grupo (1..5 + 8)
 * + a linha-mestre (V_Grupo nulo). Formato idêntico ao export real do
 * CIGAM, documentado em `docs/dre-plano-contas-formato.md`.
 *
 * Execução:
 *   php database/seeders/fixtures/generate_plano_contas_exemplo.php
 *
 * Idempotente — sobrescreve o arquivo a cada execução. Cheque os testes
 * que usam a fixture (`tests/Feature/Imports/ChartOfAccountsImportTest.php`)
 * antes de mexer no conteúdo.
 */
require __DIR__.'/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = [
    'Codigo Reduzido', 'V_Codigo plan.con', 'Tipo', 'V_Grupo',
    'Classific conta', 'Nome conta', 'Codigo Alternativo', 'Livre 14',
    'VL_Ativa', 'Natureza Saldo', 'Unidade Resultado', 'Saldo demons acu',
    'Origem conta', 'VL_Conta Resultado', 'V_Tipo LALUR',
    'V_Código Fixo LALUR', 'VL_Parte B LALUR', 'V_Funcao conta',
    'V_Funcionamento conta', 'V_Naturez Subconta', 'DescNatureza',
];

$sheet->fromArray($headers, null, 'A1');

/** Cada $n/$code/$name gera uma linha no export. */
$rows = [
    // Linha-mestre (V_Grupo nulo, code nulo).
    ['3308', '3308', 'A', null, null, null, null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],

    // Grupo 1 — ATIVO.
    ['1000', '3308', 'S', '1', '1', 'ATIVO', null, '1', 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['1001', '3308', 'S', '1', '1.1', 'ATIVO CIRCULANTE', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['1002', '3308', 'S', '1', '1.1.1', 'DISPONIVEL', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['1003', '3308', 'S', '1', '1.1.1.01', 'CAIXA GERAL', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['1004', '3308', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['1005', '3308', 'A', '1', '1.1.1.01.00017', 'CAIXA LOJAS', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],

    // Grupo 2 — PASSIVO.
    ['2000', '3308', 'S', '2', '2', 'PASSIVO', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['2001', '3308', 'S', '2', '2.1', 'PASSIVO CIRCULANTE', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['2002', '3308', 'S', '2', '2.1.1', 'FORNECEDORES', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['2003', '3308', 'A', '2', '2.1.1.01.00001', 'FORNECEDORES NACIONAIS', null, null, 'True', 'C', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],

    // Grupo 3 — RECEITAS.
    ['3000', '3308', 'S', '3', '3', 'RECEITAS', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['3001', '3308', 'S', '3', '3.1', 'RECEITAS OPERACIONAIS', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['3002', '3308', 'S', '3', '3.1.1', 'RECEITA BRUTA', null, null, 'True', 'A', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],
    ['3003', '3308', 'A', '3', '3.1.1.01.00012', 'RECEITA DE VENDAS', null, null, 'True', 'C', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],
    ['3004', '3308', 'A', '3', '3.1.1.02.00017', 'VENDAS CANCELADAS OU DEVOLVIDAS', null, null, 'True', 'D', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],

    // Grupo 4 — CUSTOS E DESPESAS.
    ['4000', '3308', 'S', '4', '4', 'CUSTOS E DESPESAS', null, null, 'True', 'A', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],
    ['4001', '3308', 'S', '4', '4.1', 'CMV', null, null, 'True', 'A', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],
    ['4002', '3308', 'A', '4', '4.1.1.02.00015', 'COMPRAS DE MERCADORIA P/ REVENDA', null, null, 'True', 'D', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],
    ['4003', '3308', 'A', '4', '4.2.1.02.00071', 'SALARIOS E ORDENADOS', null, null, 'True', 'D', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],
    ['4004', '3308', 'A', '4', '4.2.1.04.00032', 'TELEFONIA', null, null, 'True', 'D', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],

    // Grupo 5 — RESULTADO.
    ['5000', '3308', 'S', '5', '5', 'RESULTADO DO EXERCICIO', null, null, 'True', 'A', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],
    ['5001', '3308', 'A', '5', '5.1.01.00001', 'LUCRO DO EXERCICIO', null, null, 'True', 'C', '0', 'A', 'I', 'True', null, null, 'False', null, null, null, null],

    // Grupo 8 — CENTROS DE CUSTO.
    ['8119', '3308', 'S', '8', '8', 'CENTROS DE CUSTO', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['8120', '3308', 'S', '8', '8.1', 'CENTROS DE CUSTO', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['8121', '3308', 'S', '8', '8.1.01', 'MARKETING', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['8122', '3308', 'A', '8', '8.1.01.01', 'Marketing - Schutz Riomar Recife', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['8123', '3308', 'A', '8', '8.1.01.02', 'Marketing - Arezzo Kennedy', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['8130', '3308', 'S', '8', '8.1.02', 'OPERACOES', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['8131', '3308', 'A', '8', '8.1.02.01', 'Operacoes - Arezzo Kennedy', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
    ['8132', '3308', 'A', '8', '8.1.02.02', 'Operacoes - Arezzo Riomar', null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null],
];

$sheet->fromArray($rows, null, 'A2');

$writer = new Xlsx($spreadsheet);
$outputPath = __DIR__.'/plano-contas-exemplo.xlsx';
$writer->save($outputPath);

echo 'Fixture criada: '.$outputPath.PHP_EOL;
echo 'Linhas de dados: '.count($rows).PHP_EOL;
