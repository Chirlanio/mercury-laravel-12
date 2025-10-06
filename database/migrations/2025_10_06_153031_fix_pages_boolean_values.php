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
        // Corrigir valores de is_active
        // Valor 2 será considerado como inativo (0)
        DB::table('pages')
            ->where('is_active', 2)
            ->update(['is_active' => 0]);

        // Corrigir valores de is_public
        // Valor 2 será considerado como privado (0)
        DB::table('pages')
            ->where('is_public', 2)
            ->update(['is_public' => 0]);

        // Garantir que apenas valores 0 ou 1 existem
        DB::table('pages')
            ->whereNotIn('is_active', [0, 1])
            ->update(['is_active' => 0]);

        DB::table('pages')
            ->whereNotIn('is_public', [0, 1])
            ->update(['is_public' => 0]);

        // Garantir que não há valores NULL
        DB::table('pages')
            ->whereNull('is_active')
            ->update(['is_active' => 1]);

        DB::table('pages')
            ->whereNull('is_public')
            ->update(['is_public' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não há reversão, pois os dados originais estavam incorretos
    }
};
