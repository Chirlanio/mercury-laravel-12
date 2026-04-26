<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renomeia o tipo de pausa "Intervalo" → "Lanche" para tenants já
 * provisionados. O migration original (700001) já cria com o nome novo
 * para tenants futuros — esta migration apenas alinha os antigos.
 *
 * Idempotente: WHERE name='Intervalo' não bate em registros já com o
 * nome novo nem tenants sem o módulo TurnList.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('turn_list_break_types')) {
            return;
        }

        DB::table('turn_list_break_types')
            ->where('name', 'Intervalo')
            ->update([
                'name' => 'Lanche',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('turn_list_break_types')) {
            return;
        }

        DB::table('turn_list_break_types')
            ->where('name', 'Lanche')
            ->update([
                'name' => 'Intervalo',
                'updated_at' => now(),
            ]);
    }
};
