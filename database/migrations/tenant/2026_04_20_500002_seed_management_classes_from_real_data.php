<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed de Classes Gerenciais — 169 classes analíticas extraídas do razão
 * gerencial 05/2023 do Grupo Meia Sola.
 *
 * Padrão do código: 8.1.DD.UU
 *   DD = departamento (01..11)
 *   UU = unidade de negócio (02..24)
 *
 * Estrutura sintética criada:
 *   8           (raiz "Classes Gerenciais")
 *   └ 8.1       (Operacional)
 *     ├ 8.1.01  Marketing
 *     ├ 8.1.02  Operações
 *     ├ 8.1.03  Planejamento
 *     ├ 8.1.04  Gente e Gestão
 *     ├ 8.1.05  DP (Departamento Pessoal)
 *     ├ 8.1.06  Financeiro
 *     ├ 8.1.07  Fiscal
 *     ├ 8.1.08  TI
 *     ├ 8.1.09  Comercial
 *     ├ 8.1.10  CFO
 *     └ 8.1.11  Diretoria
 *
 * Cada analítica vincula `cost_center_id` ao CC mais frequente nos lançamentos
 * reais (ex: "Comercial - Arezzo Kennedy" → CC 422). Isso pré-configura a
 * relação para o módulo Budgets resolver no parser de orçamento.
 *
 * Depende de:
 *   - 2026_04_20_100002_seed_cost_centers_from_real_data.php (CCs 422-457)
 *   - 2026_04_20_500001_create_management_classes_table.php (tabela)
 *
 * Idempotente: skipa se já existe a classe 8.1.01.02.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_classes')) {
            return;
        }
        if (DB::table('management_classes')->where('code', '8.1.01.02')->exists()) {
            return;
        }

        // Resolve cost_centers.code => id (para preencher cost_center_id)
        $ccMap = DB::table('cost_centers')->pluck('id', 'code')->toArray();

        $now = now();
        $ids = [];

        $insert = function (array $row) use (&$ids, $now, $ccMap) {
            $row = array_merge([
                'parent_id' => null,
                'description' => null,
                'accounting_class_id' => null,
                'cost_center_id' => null,
                'sort_order' => 0,
                'accepts_entries' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $row);

            if (! empty($row['parent_code'])) {
                $row['parent_id'] = $ids[$row['parent_code']] ?? null;
            }
            if (! empty($row['cost_center_code']) && isset($ccMap[$row['cost_center_code']])) {
                $row['cost_center_id'] = $ccMap[$row['cost_center_code']];
            }
            unset($row['parent_code'], $row['cost_center_code']);

            $id = DB::table('management_classes')->insertGetId($row);
            $ids[$row['code']] = $id;
        };

        // Sintéticos: raiz 8, sub-operacional 8.1, 11 departamentos 8.1.XX
        $synthetics = [
                ['code' => '8', 'name' => 'Classes Gerenciais', 'parent_code' => NULL, 'sort_order' => 1],
                ['code' => '8.1', 'name' => 'Operacional', 'parent_code' => '8', 'sort_order' => 5],
                ['code' => '8.1.01', 'name' => 'Marketing', 'parent_code' => '8.1', 'sort_order' => 10],
                ['code' => '8.1.02', 'name' => 'Operações', 'parent_code' => '8.1', 'sort_order' => 20],
                ['code' => '8.1.03', 'name' => 'Planejamento', 'parent_code' => '8.1', 'sort_order' => 30],
                ['code' => '8.1.04', 'name' => 'Gente e Gestão', 'parent_code' => '8.1', 'sort_order' => 40],
                ['code' => '8.1.05', 'name' => 'DP', 'parent_code' => '8.1', 'sort_order' => 50],
                ['code' => '8.1.06', 'name' => 'Financeiro', 'parent_code' => '8.1', 'sort_order' => 60],
                ['code' => '8.1.07', 'name' => 'Fiscal', 'parent_code' => '8.1', 'sort_order' => 70],
                ['code' => '8.1.08', 'name' => 'TI', 'parent_code' => '8.1', 'sort_order' => 80],
                ['code' => '8.1.09', 'name' => 'Comercial', 'parent_code' => '8.1', 'sort_order' => 90],
                ['code' => '8.1.10', 'name' => 'CFO', 'parent_code' => '8.1', 'sort_order' => 100],
                ['code' => '8.1.11', 'name' => 'Diretoria', 'parent_code' => '8.1', 'sort_order' => 110],
            ];

        foreach ($synthetics as $s) {
            $insert($s + ['accepts_entries' => false]);
        }

        // 169 analíticas — uma por (departamento × unidade)
        $analyticals = [
                ['code' => '8.1.01.02', 'name' => 'Marketing - Arezzo Kennedy', 'parent_code' => '8.1.01', 'cost_center_code' => 422, 'sort_order' => 205],
                ['code' => '8.1.01.03', 'name' => 'Marketing - Arezzo 90', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 210],
                ['code' => '8.1.01.04', 'name' => 'Marketing - Arezzo 408', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 215],
                ['code' => '8.1.01.05', 'name' => 'Marketing - Arezzo Riomar', 'parent_code' => '8.1.01', 'cost_center_code' => 422, 'sort_order' => 220],
                ['code' => '8.1.01.06', 'name' => 'Marketing - Arezzo Dom Luis', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 225],
                ['code' => '8.1.01.07', 'name' => 'Marketing - Arezzo Cariri', 'parent_code' => '8.1.01', 'cost_center_code' => 422, 'sort_order' => 230],
                ['code' => '8.1.01.08', 'name' => 'Marketing - Arezzo Sobral', 'parent_code' => '8.1.01', 'cost_center_code' => 428, 'sort_order' => 235],
                ['code' => '8.1.01.09', 'name' => 'Marketing - Meia Sola Maison', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 240],
                ['code' => '8.1.01.10', 'name' => 'Marketing - Meia Sola Riomar', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 245],
                ['code' => '8.1.01.11', 'name' => 'Marketing - Meia Sola Aldeota', 'parent_code' => '8.1.01', 'cost_center_code' => 431, 'sort_order' => 250],
                ['code' => '8.1.01.12', 'name' => 'Marketing - Meia Sola Iguatemi', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 255],
                ['code' => '8.1.01.13', 'name' => 'Marketing - Off Caucaia', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 260],
                ['code' => '8.1.01.14', 'name' => 'Marketing - AnaCapri Aldeota', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 265],
                ['code' => '8.1.01.16', 'name' => 'Marketing - AnaCapri Iguatemi', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 270],
                ['code' => '8.1.01.17', 'name' => 'Marketing - Anacapri Riomar', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 275],
                ['code' => '8.1.01.18', 'name' => 'Marketing - Schutz Aldeota', 'parent_code' => '8.1.01', 'cost_center_code' => 422, 'sort_order' => 280],
                ['code' => '8.1.01.19', 'name' => 'Marketing - Schutz Riomar', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 285],
                ['code' => '8.1.01.20', 'name' => 'Marketing - Schutz Iguatemi', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 290],
                ['code' => '8.1.01.21', 'name' => 'Marketing - E-Commerce', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 295],
                ['code' => '8.1.01.23', 'name' => 'Marketing - Geral', 'parent_code' => '8.1.01', 'cost_center_code' => 429, 'sort_order' => 300],
                ['code' => '8.1.02.02', 'name' => 'Operações - Arezzo Kennedy', 'parent_code' => '8.1.02', 'cost_center_code' => 422, 'sort_order' => 305],
                ['code' => '8.1.02.03', 'name' => 'Operações - Arezzo 90', 'parent_code' => '8.1.02', 'cost_center_code' => 423, 'sort_order' => 310],
                ['code' => '8.1.02.04', 'name' => 'Operações - Arezzo 408', 'parent_code' => '8.1.02', 'cost_center_code' => 424, 'sort_order' => 315],
                ['code' => '8.1.02.05', 'name' => 'Operações - Arezzo Riomar', 'parent_code' => '8.1.02', 'cost_center_code' => 425, 'sort_order' => 320],
                ['code' => '8.1.02.06', 'name' => 'Operações - Arezzo Dom Luis', 'parent_code' => '8.1.02', 'cost_center_code' => 429, 'sort_order' => 325],
                ['code' => '8.1.02.07', 'name' => 'Operações - Arezzo Cariri', 'parent_code' => '8.1.02', 'cost_center_code' => 427, 'sort_order' => 330],
                ['code' => '8.1.02.08', 'name' => 'Operações - Arezzo Sobral', 'parent_code' => '8.1.02', 'cost_center_code' => 428, 'sort_order' => 335],
                ['code' => '8.1.02.09', 'name' => 'Operações - Meia Sola Maison', 'parent_code' => '8.1.02', 'cost_center_code' => 429, 'sort_order' => 340],
                ['code' => '8.1.02.10', 'name' => 'Operações - Meia Sola Riomar', 'parent_code' => '8.1.02', 'cost_center_code' => 430, 'sort_order' => 345],
                ['code' => '8.1.02.11', 'name' => 'Operações - Meia Sola Aldeota', 'parent_code' => '8.1.02', 'cost_center_code' => 429, 'sort_order' => 350],
                ['code' => '8.1.02.12', 'name' => 'Operações - Meia Sola Iguatemi', 'parent_code' => '8.1.02', 'cost_center_code' => 432, 'sort_order' => 355],
                ['code' => '8.1.02.13', 'name' => 'Operações - Off Caucaia', 'parent_code' => '8.1.02', 'cost_center_code' => 422, 'sort_order' => 360],
                ['code' => '8.1.02.14', 'name' => 'Operações - AnaCapri Aldeota', 'parent_code' => '8.1.02', 'cost_center_code' => 429, 'sort_order' => 365],
                ['code' => '8.1.02.16', 'name' => 'Operações - AnaCapri Iguatemi', 'parent_code' => '8.1.02', 'cost_center_code' => 436, 'sort_order' => 370],
                ['code' => '8.1.02.17', 'name' => 'Operações - Anacapri Riomar', 'parent_code' => '8.1.02', 'cost_center_code' => 437, 'sort_order' => 375],
                ['code' => '8.1.02.19', 'name' => 'Operações - Schutz Riomar', 'parent_code' => '8.1.02', 'cost_center_code' => 439, 'sort_order' => 380],
                ['code' => '8.1.02.20', 'name' => 'Operações - Schutz Iguatemi', 'parent_code' => '8.1.02', 'cost_center_code' => 422, 'sort_order' => 385],
                ['code' => '8.1.02.21', 'name' => 'Operações - E-Commerce', 'parent_code' => '8.1.02', 'cost_center_code' => 429, 'sort_order' => 390],
                ['code' => '8.1.02.22', 'name' => 'Operações - Qualidade', 'parent_code' => '8.1.02', 'cost_center_code' => 442, 'sort_order' => 395],
                ['code' => '8.1.02.23', 'name' => 'Operações - Geral', 'parent_code' => '8.1.02', 'cost_center_code' => 422, 'sort_order' => 400],
                ['code' => '8.1.02.24', 'name' => 'Operações - CD', 'parent_code' => '8.1.02', 'cost_center_code' => 422, 'sort_order' => 405],
                ['code' => '8.1.03.02', 'name' => 'Planejamento - Arezzo Kennedy', 'parent_code' => '8.1.03', 'cost_center_code' => 422, 'sort_order' => 410],
                ['code' => '8.1.03.03', 'name' => 'Planejamento - Arezzo 90', 'parent_code' => '8.1.03', 'cost_center_code' => 423, 'sort_order' => 415],
                ['code' => '8.1.03.04', 'name' => 'Planejamento - Arezzo 408', 'parent_code' => '8.1.03', 'cost_center_code' => 424, 'sort_order' => 420],
                ['code' => '8.1.03.05', 'name' => 'Planejamento - Arezzo Riomar', 'parent_code' => '8.1.03', 'cost_center_code' => 425, 'sort_order' => 425],
                ['code' => '8.1.03.06', 'name' => 'Planejamento - Arezzo Dom Luis', 'parent_code' => '8.1.03', 'cost_center_code' => 426, 'sort_order' => 430],
                ['code' => '8.1.03.07', 'name' => 'Planejamento - Arezzo Cariri', 'parent_code' => '8.1.03', 'cost_center_code' => 427, 'sort_order' => 435],
                ['code' => '8.1.03.08', 'name' => 'Planejamento - Arezzo Sobral', 'parent_code' => '8.1.03', 'cost_center_code' => 428, 'sort_order' => 440],
                ['code' => '8.1.03.09', 'name' => 'Planejamento - Meia Sola Maison', 'parent_code' => '8.1.03', 'cost_center_code' => 429, 'sort_order' => 445],
                ['code' => '8.1.03.10', 'name' => 'Planejamento - Meia Sola Riomar', 'parent_code' => '8.1.03', 'cost_center_code' => 430, 'sort_order' => 450],
                ['code' => '8.1.03.11', 'name' => 'Planejamento - Meia Sola Aldeota', 'parent_code' => '8.1.03', 'cost_center_code' => 431, 'sort_order' => 455],
                ['code' => '8.1.03.12', 'name' => 'Planejamento - Meia Sola Iguatemi', 'parent_code' => '8.1.03', 'cost_center_code' => 432, 'sort_order' => 460],
                ['code' => '8.1.03.13', 'name' => 'Planejamento - Off Caucaia', 'parent_code' => '8.1.03', 'cost_center_code' => 433, 'sort_order' => 465],
                ['code' => '8.1.03.14', 'name' => 'Planejamento - AnaCapri Aldeota', 'parent_code' => '8.1.03', 'cost_center_code' => 434, 'sort_order' => 470],
                ['code' => '8.1.03.16', 'name' => 'Planejamento - AnaCapri Iguatemi', 'parent_code' => '8.1.03', 'cost_center_code' => 436, 'sort_order' => 475],
                ['code' => '8.1.03.17', 'name' => 'Planejamento - Anacapri Riomar', 'parent_code' => '8.1.03', 'cost_center_code' => 437, 'sort_order' => 480],
                ['code' => '8.1.03.18', 'name' => 'Planejamento - Schutz Aldeota', 'parent_code' => '8.1.03', 'cost_center_code' => 435, 'sort_order' => 485],
                ['code' => '8.1.03.19', 'name' => 'Planejamento - Schutz Riomar', 'parent_code' => '8.1.03', 'cost_center_code' => 439, 'sort_order' => 490],
                ['code' => '8.1.03.20', 'name' => 'Planejamento - Schutz Iguatemi', 'parent_code' => '8.1.03', 'cost_center_code' => 440, 'sort_order' => 495],
                ['code' => '8.1.03.21', 'name' => 'Planejamento - E-Commerce', 'parent_code' => '8.1.03', 'cost_center_code' => 441, 'sort_order' => 500],
                ['code' => '8.1.03.22', 'name' => 'Planejamento - Qualidade', 'parent_code' => '8.1.03', 'cost_center_code' => 442, 'sort_order' => 505],
                ['code' => '8.1.03.23', 'name' => 'Planejamento - Geral', 'parent_code' => '8.1.03', 'cost_center_code' => 442, 'sort_order' => 510],
                ['code' => '8.1.03.24', 'name' => 'Planejamento - CD', 'parent_code' => '8.1.03', 'cost_center_code' => 443, 'sort_order' => 515],
                ['code' => '8.1.04.23', 'name' => 'Gente e Gestão - Geral', 'parent_code' => '8.1.04', 'cost_center_code' => 429, 'sort_order' => 520],
                ['code' => '8.1.05.02', 'name' => 'DP - Arezzo Kennedy', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 525],
                ['code' => '8.1.05.03', 'name' => 'DP - Arezzo 90', 'parent_code' => '8.1.05', 'cost_center_code' => 423, 'sort_order' => 530],
                ['code' => '8.1.05.04', 'name' => 'DP - Arezzo 408', 'parent_code' => '8.1.05', 'cost_center_code' => 424, 'sort_order' => 535],
                ['code' => '8.1.05.05', 'name' => 'DP - Arezzo Riomar', 'parent_code' => '8.1.05', 'cost_center_code' => 425, 'sort_order' => 540],
                ['code' => '8.1.05.06', 'name' => 'DP - Arezzo Dom Luis', 'parent_code' => '8.1.05', 'cost_center_code' => 426, 'sort_order' => 545],
                ['code' => '8.1.05.07', 'name' => 'DP - Arezzo Cariri', 'parent_code' => '8.1.05', 'cost_center_code' => 427, 'sort_order' => 550],
                ['code' => '8.1.05.08', 'name' => 'DP - Arezzo Sobral', 'parent_code' => '8.1.05', 'cost_center_code' => 428, 'sort_order' => 555],
                ['code' => '8.1.05.09', 'name' => 'DP - Meia Sola Maison', 'parent_code' => '8.1.05', 'cost_center_code' => 429, 'sort_order' => 560],
                ['code' => '8.1.05.10', 'name' => 'DP - Meia Sola Riomar', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 565],
                ['code' => '8.1.05.11', 'name' => 'DP - Meia Sola Aldeota', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 570],
                ['code' => '8.1.05.12', 'name' => 'DP - Meia Sola Iguatemi', 'parent_code' => '8.1.05', 'cost_center_code' => 432, 'sort_order' => 575],
                ['code' => '8.1.05.13', 'name' => 'DP - Meia Sola Off Caucaia', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 580],
                ['code' => '8.1.05.14', 'name' => 'DP - AnaCapri Aldeota', 'parent_code' => '8.1.05', 'cost_center_code' => 434, 'sort_order' => 585],
                ['code' => '8.1.05.16', 'name' => 'DP - AnaCapri Iguatemi', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 590],
                ['code' => '8.1.05.17', 'name' => 'DP - Anacapri Riomar', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 595],
                ['code' => '8.1.05.18', 'name' => 'DP - Schutz Aldeota', 'parent_code' => '8.1.05', 'cost_center_code' => 438, 'sort_order' => 600],
                ['code' => '8.1.05.19', 'name' => 'DP - Schutz Riomar', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 605],
                ['code' => '8.1.05.20', 'name' => 'DP - Schutz Iguatemi', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 610],
                ['code' => '8.1.05.21', 'name' => 'DP - E-Commerce', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 615],
                ['code' => '8.1.05.23', 'name' => 'DP - Geral', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 620],
                ['code' => '8.1.05.24', 'name' => 'DP - CD', 'parent_code' => '8.1.05', 'cost_center_code' => 422, 'sort_order' => 625],
                ['code' => '8.1.06.02', 'name' => 'Financeiro - Arezzo Kennedy', 'parent_code' => '8.1.06', 'cost_center_code' => 422, 'sort_order' => 630],
                ['code' => '8.1.06.03', 'name' => 'Financeiro - Arezzo 90', 'parent_code' => '8.1.06', 'cost_center_code' => 423, 'sort_order' => 635],
                ['code' => '8.1.06.04', 'name' => 'Financeiro - Arezzo 408', 'parent_code' => '8.1.06', 'cost_center_code' => 424, 'sort_order' => 640],
                ['code' => '8.1.06.05', 'name' => 'Financeiro - Arezzo Riomar', 'parent_code' => '8.1.06', 'cost_center_code' => 425, 'sort_order' => 645],
                ['code' => '8.1.06.06', 'name' => 'Financeiro - Arezzo Dom Luis', 'parent_code' => '8.1.06', 'cost_center_code' => 426, 'sort_order' => 650],
                ['code' => '8.1.06.07', 'name' => 'Financeiro - Arezzo Cariri', 'parent_code' => '8.1.06', 'cost_center_code' => 427, 'sort_order' => 655],
                ['code' => '8.1.06.08', 'name' => 'Financeiro - Arezzo Sobral', 'parent_code' => '8.1.06', 'cost_center_code' => 428, 'sort_order' => 660],
                ['code' => '8.1.06.10', 'name' => 'Financeiro - Meia Sola Riomar', 'parent_code' => '8.1.06', 'cost_center_code' => 430, 'sort_order' => 665],
                ['code' => '8.1.06.11', 'name' => 'Financeiro - Meia Sola Aldeota', 'parent_code' => '8.1.06', 'cost_center_code' => 431, 'sort_order' => 670],
                ['code' => '8.1.06.12', 'name' => 'Financeiro - Meia Sola Iguatemi', 'parent_code' => '8.1.06', 'cost_center_code' => 432, 'sort_order' => 675],
                ['code' => '8.1.06.14', 'name' => 'Financeiro - AnaCapri Aldeota', 'parent_code' => '8.1.06', 'cost_center_code' => 434, 'sort_order' => 680],
                ['code' => '8.1.06.16', 'name' => 'Financeiro - AnaCapri Iguatemi', 'parent_code' => '8.1.06', 'cost_center_code' => 436, 'sort_order' => 685],
                ['code' => '8.1.06.17', 'name' => 'Financeiro - Anacapri Riomar', 'parent_code' => '8.1.06', 'cost_center_code' => 437, 'sort_order' => 690],
                ['code' => '8.1.06.19', 'name' => 'Financeiro - Schutz Riomar', 'parent_code' => '8.1.06', 'cost_center_code' => 439, 'sort_order' => 695],
                ['code' => '8.1.06.20', 'name' => 'Financeiro - Schutz Iguatemi', 'parent_code' => '8.1.06', 'cost_center_code' => 440, 'sort_order' => 700],
                ['code' => '8.1.06.21', 'name' => 'Financeiro - E-Commerce', 'parent_code' => '8.1.06', 'cost_center_code' => 441, 'sort_order' => 705],
                ['code' => '8.1.06.23', 'name' => 'Financeiro - Geral', 'parent_code' => '8.1.06', 'cost_center_code' => 442, 'sort_order' => 710],
                ['code' => '8.1.07.01', 'name' => 'Fiscal - Arezzo Centro', 'parent_code' => '8.1.07', 'cost_center_code' => 421, 'sort_order' => 715],
                ['code' => '8.1.07.02', 'name' => 'Fiscal - Arezzo Kennedy', 'parent_code' => '8.1.07', 'cost_center_code' => 422, 'sort_order' => 720],
                ['code' => '8.1.07.03', 'name' => 'Fiscal - Arezzo 90', 'parent_code' => '8.1.07', 'cost_center_code' => 423, 'sort_order' => 725],
                ['code' => '8.1.07.04', 'name' => 'Fiscal - Arezzo 408', 'parent_code' => '8.1.07', 'cost_center_code' => 424, 'sort_order' => 730],
                ['code' => '8.1.07.05', 'name' => 'Fiscal - Arezzo Riomar', 'parent_code' => '8.1.07', 'cost_center_code' => 425, 'sort_order' => 735],
                ['code' => '8.1.07.06', 'name' => 'Fiscal - Arezzo Dom Luis', 'parent_code' => '8.1.07', 'cost_center_code' => 426, 'sort_order' => 740],
                ['code' => '8.1.07.07', 'name' => 'Fiscal - Arezzo Cariri', 'parent_code' => '8.1.07', 'cost_center_code' => 427, 'sort_order' => 745],
                ['code' => '8.1.07.08', 'name' => 'Fiscal - Arezzo Sobral', 'parent_code' => '8.1.07', 'cost_center_code' => 428, 'sort_order' => 750],
                ['code' => '8.1.07.09', 'name' => 'Fiscal - Meia Sola Maison', 'parent_code' => '8.1.07', 'cost_center_code' => 429, 'sort_order' => 755],
                ['code' => '8.1.07.10', 'name' => 'Fiscal - Meia Sola Riomar', 'parent_code' => '8.1.07', 'cost_center_code' => 430, 'sort_order' => 760],
                ['code' => '8.1.07.11', 'name' => 'Fiscal - Meia Sola Aldeota', 'parent_code' => '8.1.07', 'cost_center_code' => 431, 'sort_order' => 765],
                ['code' => '8.1.07.12', 'name' => 'Fiscal - Meia Sola Iguatemi', 'parent_code' => '8.1.07', 'cost_center_code' => 432, 'sort_order' => 770],
                ['code' => '8.1.07.13', 'name' => 'Fiscal - Off Caucaia', 'parent_code' => '8.1.07', 'cost_center_code' => 433, 'sort_order' => 775],
                ['code' => '8.1.07.14', 'name' => 'Fiscal - AnaCapri Aldeota', 'parent_code' => '8.1.07', 'cost_center_code' => 434, 'sort_order' => 780],
                ['code' => '8.1.07.16', 'name' => 'Fiscal - AnaCapri Iguatemi', 'parent_code' => '8.1.07', 'cost_center_code' => 436, 'sort_order' => 785],
                ['code' => '8.1.07.17', 'name' => 'Fiscal - Anacapri Riomar', 'parent_code' => '8.1.07', 'cost_center_code' => 437, 'sort_order' => 790],
                ['code' => '8.1.07.18', 'name' => 'Fiscal - Schutz Aldeota', 'parent_code' => '8.1.07', 'cost_center_code' => 438, 'sort_order' => 795],
                ['code' => '8.1.07.19', 'name' => 'Fiscal - Schutz Riomar', 'parent_code' => '8.1.07', 'cost_center_code' => 439, 'sort_order' => 800],
                ['code' => '8.1.07.20', 'name' => 'Fiscal - Schutz Iguatemi', 'parent_code' => '8.1.07', 'cost_center_code' => 440, 'sort_order' => 805],
                ['code' => '8.1.07.21', 'name' => 'Fiscal - E-Commerce', 'parent_code' => '8.1.07', 'cost_center_code' => 441, 'sort_order' => 810],
                ['code' => '8.1.07.22', 'name' => 'Fiscal - Qualidade', 'parent_code' => '8.1.07', 'cost_center_code' => 442, 'sort_order' => 815],
                ['code' => '8.1.07.23', 'name' => 'Fiscal - Geral', 'parent_code' => '8.1.07', 'cost_center_code' => 422, 'sort_order' => 820],
                ['code' => '8.1.07.24', 'name' => 'Fiscal - CD', 'parent_code' => '8.1.07', 'cost_center_code' => 443, 'sort_order' => 825],
                ['code' => '8.1.07.25', 'name' => 'Fiscal - BRIZZA', 'parent_code' => '8.1.07', 'cost_center_code' => 457, 'sort_order' => 830],
                ['code' => '8.1.08.02', 'name' => 'TI - Arezzo Kennedy', 'parent_code' => '8.1.08', 'cost_center_code' => 422, 'sort_order' => 835],
                ['code' => '8.1.08.03', 'name' => 'TI - Arezzo 90', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 840],
                ['code' => '8.1.08.04', 'name' => 'TI - Arezzo 408', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 845],
                ['code' => '8.1.08.05', 'name' => 'TI - Arezzo Riomar', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 850],
                ['code' => '8.1.08.06', 'name' => 'TI - Arezzo Dom Luis', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 855],
                ['code' => '8.1.08.07', 'name' => 'TI - Arezzo Cariri', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 860],
                ['code' => '8.1.08.08', 'name' => 'TI - Arezzo Sobral', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 865],
                ['code' => '8.1.08.09', 'name' => 'TI - Meia Sola Maison', 'parent_code' => '8.1.08', 'cost_center_code' => 422, 'sort_order' => 870],
                ['code' => '8.1.08.10', 'name' => 'TI - Meia Sola Riomar', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 875],
                ['code' => '8.1.08.11', 'name' => 'TI - Meia Sola Aldeota', 'parent_code' => '8.1.08', 'cost_center_code' => 431, 'sort_order' => 880],
                ['code' => '8.1.08.12', 'name' => 'TI - Meia Sola Iguatemi', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 885],
                ['code' => '8.1.08.13', 'name' => 'TI - Off Caucaia', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 890],
                ['code' => '8.1.08.14', 'name' => 'TI - AnaCapri Aldeota', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 895],
                ['code' => '8.1.08.16', 'name' => 'TI - AnaCapri Iguatemi', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 900],
                ['code' => '8.1.08.17', 'name' => 'TI - Anacapri Riomar', 'parent_code' => '8.1.08', 'cost_center_code' => 429, 'sort_order' => 905],
                ['code' => '8.1.08.19', 'name' => 'TI - Schutz Riomar', 'parent_code' => '8.1.08', 'cost_center_code' => 439, 'sort_order' => 910],
                ['code' => '8.1.08.20', 'name' => 'TI - Schutz Iguatemi', 'parent_code' => '8.1.08', 'cost_center_code' => 440, 'sort_order' => 915],
                ['code' => '8.1.08.21', 'name' => 'TI - E-Commerce', 'parent_code' => '8.1.08', 'cost_center_code' => 441, 'sort_order' => 920],
                ['code' => '8.1.08.22', 'name' => 'TI - Qualidade', 'parent_code' => '8.1.08', 'cost_center_code' => 442, 'sort_order' => 925],
                ['code' => '8.1.08.23', 'name' => 'TI - Geral', 'parent_code' => '8.1.08', 'cost_center_code' => 422, 'sort_order' => 930],
                ['code' => '8.1.08.24', 'name' => 'TI - CD', 'parent_code' => '8.1.08', 'cost_center_code' => 443, 'sort_order' => 935],
                ['code' => '8.1.09.02', 'name' => 'Comercial - Arezzo Kennedy', 'parent_code' => '8.1.09', 'cost_center_code' => 422, 'sort_order' => 940],
                ['code' => '8.1.09.03', 'name' => 'Comercial - Arezzo 90', 'parent_code' => '8.1.09', 'cost_center_code' => 423, 'sort_order' => 945],
                ['code' => '8.1.09.04', 'name' => 'Comercial - Arezzo 408', 'parent_code' => '8.1.09', 'cost_center_code' => 424, 'sort_order' => 950],
                ['code' => '8.1.09.05', 'name' => 'Comercial - Arezzo Riomar', 'parent_code' => '8.1.09', 'cost_center_code' => 425, 'sort_order' => 955],
                ['code' => '8.1.09.06', 'name' => 'Comercial - Arezzo Dom Luis', 'parent_code' => '8.1.09', 'cost_center_code' => 426, 'sort_order' => 960],
                ['code' => '8.1.09.07', 'name' => 'Comercial - Arezzo Cariri', 'parent_code' => '8.1.09', 'cost_center_code' => 427, 'sort_order' => 965],
                ['code' => '8.1.09.08', 'name' => 'Comercial - Arezzo Sobral', 'parent_code' => '8.1.09', 'cost_center_code' => 428, 'sort_order' => 970],
                ['code' => '8.1.09.09', 'name' => 'Comercial - Meia Sola Maison', 'parent_code' => '8.1.09', 'cost_center_code' => 429, 'sort_order' => 975],
                ['code' => '8.1.09.10', 'name' => 'Comercial - Meia Sola Riomar', 'parent_code' => '8.1.09', 'cost_center_code' => 430, 'sort_order' => 980],
                ['code' => '8.1.09.11', 'name' => 'Comercial - Meia Sola Aldeota', 'parent_code' => '8.1.09', 'cost_center_code' => 431, 'sort_order' => 985],
                ['code' => '8.1.09.12', 'name' => 'Comercial - Meia Sola Iguatemi', 'parent_code' => '8.1.09', 'cost_center_code' => 432, 'sort_order' => 990],
                ['code' => '8.1.09.13', 'name' => 'Comercial - Off Caucaia', 'parent_code' => '8.1.09', 'cost_center_code' => 433, 'sort_order' => 995],
                ['code' => '8.1.09.14', 'name' => 'Comercial - AnaCapri Aldeota', 'parent_code' => '8.1.09', 'cost_center_code' => 434, 'sort_order' => 1000],
                ['code' => '8.1.09.16', 'name' => 'Comercial - AnaCapri Iguatemi', 'parent_code' => '8.1.09', 'cost_center_code' => 436, 'sort_order' => 1005],
                ['code' => '8.1.09.17', 'name' => 'Comercial - Anacapri Riomar', 'parent_code' => '8.1.09', 'cost_center_code' => 437, 'sort_order' => 1010],
                ['code' => '8.1.09.19', 'name' => 'Comercial - Schutz Riomar', 'parent_code' => '8.1.09', 'cost_center_code' => 439, 'sort_order' => 1015],
                ['code' => '8.1.09.20', 'name' => 'Comercial - Schutz Iguatemi', 'parent_code' => '8.1.09', 'cost_center_code' => 440, 'sort_order' => 1020],
                ['code' => '8.1.09.21', 'name' => 'Comercial - E-Commerce', 'parent_code' => '8.1.09', 'cost_center_code' => 441, 'sort_order' => 1025],
                ['code' => '8.1.10.23', 'name' => 'CFO - Geral', 'parent_code' => '8.1.10', 'cost_center_code' => 422, 'sort_order' => 1030],
                ['code' => '8.1.11.02', 'name' => 'Diretoria - Arezzo Kennedy', 'parent_code' => '8.1.11', 'cost_center_code' => 422, 'sort_order' => 1035],
                ['code' => '8.1.11.23', 'name' => 'Diretoria - Geral', 'parent_code' => '8.1.11', 'cost_center_code' => 429, 'sort_order' => 1040],
                ['code' => '8.1.11.24', 'name' => 'Diretoria - CD', 'parent_code' => '8.1.11', 'cost_center_code' => 442, 'sort_order' => 1045],
            ];

        foreach ($analyticals as $a) {
            $insert($a);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_classes')) {
            return;
        }
        DB::table('management_classes')->truncate();
    }
};