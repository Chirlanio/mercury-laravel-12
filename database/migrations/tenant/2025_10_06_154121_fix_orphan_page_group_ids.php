<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Corrigir páginas com page_group_id = 9 (órfão)
        // O grupo "Pesquisar" correto tem ID 8
        DB::table('pages')
            ->where('page_group_id', 9)
            ->update(['page_group_id' => 8]);

        // Verificar e corrigir outros possíveis IDs órfãos
        // Buscar IDs de grupos que existem nas páginas mas não na tabela page_groups
        $validGroupIds = DB::table('page_groups')->pluck('id')->toArray();
        $orphanGroupIds = DB::table('pages')
            ->select('page_group_id')
            ->distinct()
            ->whereNotNull('page_group_id')
            ->whereNotIn('page_group_id', $validGroupIds)
            ->pluck('page_group_id')
            ->toArray();

        // Para cada ID órfão, atribuir ao grupo "Outros" (ID 6)
        if (!empty($orphanGroupIds)) {
            DB::table('pages')
                ->whereIn('page_group_id', $orphanGroupIds)
                ->update(['page_group_id' => 6]); // 6 = Outros
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não há reversão, pois os dados originais estavam incorretos
    }
};
