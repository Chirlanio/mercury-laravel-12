<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed de Centros de Custo — 24 unidades derivadas do razão gerencial
 * histórico 05/2023 (Grupo Meia Sola).
 *
 * 21 lojas (421–443, 457) + 3 estruturas transversais:
 *   - 442: Qualidade
 *   - 443: Geral
 *   - 457: CD (Centro de Distribuição)
 *
 * Resolve o `name` via tabela `stores` quando houver match por `code`
 * sem o prefixo "Z" (cigam usa Z422, cost center usa 422). Fallback para
 * o nome canônico derivado das classes gerenciais da planilha.
 *
 * Idempotente: skipa se já existir CC 422.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cost_centers')) {
            return;
        }
        if (DB::table('cost_centers')->where('code', '422')->exists()) {
            return;
        }

        $costCenters = [
                ['code' => '421', 'name' => 'Arezzo Centro'],
                ['code' => '422', 'name' => 'Arezzo Kennedy'],
                ['code' => '423', 'name' => 'Arezzo 90'],
                ['code' => '424', 'name' => 'Arezzo 408'],
                ['code' => '425', 'name' => 'Arezzo Riomar'],
                ['code' => '426', 'name' => 'Arezzo Dom Luís'],
                ['code' => '427', 'name' => 'Arezzo Cariri'],
                ['code' => '428', 'name' => 'Arezzo Sobral'],
                ['code' => '429', 'name' => 'Meia Sola Maison'],
                ['code' => '430', 'name' => 'Meia Sola Riomar'],
                ['code' => '431', 'name' => 'Meia Sola Aldeota'],
                ['code' => '432', 'name' => 'Meia Sola Iguatemi'],
                ['code' => '433', 'name' => 'Off Caucaia'],
                ['code' => '434', 'name' => 'AnaCapri Aldeota'],
                ['code' => '435', 'name' => 'AnaCapri Sobral'],
                ['code' => '436', 'name' => 'AnaCapri Iguatemi'],
                ['code' => '437', 'name' => 'AnaCapri Riomar'],
                ['code' => '438', 'name' => 'Schutz Aldeota'],
                ['code' => '439', 'name' => 'Schutz Riomar'],
                ['code' => '440', 'name' => 'Schutz Iguatemi'],
                ['code' => '441', 'name' => 'E-Commerce'],
                ['code' => '442', 'name' => 'Qualidade'],
                ['code' => '443', 'name' => 'Geral'],
                ['code' => '457', 'name' => 'CD (Centro de Distribuição)'],
            ];

        // Mapa "codigo sem Z" => nome da loja (para resolver o name via stores)
        $storeNames = [];
        if (Schema::hasTable('stores')) {
            foreach (DB::table('stores')->get(['code', 'name']) as $s) {
                $storeNames[ltrim($s->code, 'Zz')] = $s->name;
            }
        }

        $now = now();
        $sortOrder = 0;

        foreach ($costCenters as $cc) {
            // Skipa se alguém já cadastrou o CC manualmente (não sobrescreve)
            if (DB::table('cost_centers')->where('code', $cc['code'])->exists()) {
                continue;
            }

            $name = $storeNames[$cc['code']] ?? $cc['name'];

            DB::table('cost_centers')->insert([
                'code' => $cc['code'],
                'name' => $name,
                'description' => null,
                'area_id' => null,
                'parent_id' => null,
                'default_accounting_class_id' => null,
                'manager_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sortOrder += 10;
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cost_centers')) {
            return;
        }
        DB::table('cost_centers')
            ->whereIn('code', ['421','422','423','424','425','426','427','428','429','430','431','432','433','434','435','436','437','438','439','440','441','442','443','457'])
            ->delete();
    }
};