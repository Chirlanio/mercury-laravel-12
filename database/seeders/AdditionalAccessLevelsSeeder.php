<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdditionalAccessLevelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $accessLevels = [
            ['name' => 'Super Administrador', 'order' => 1, 'color_theme_id' => 4],
            ['name' => 'Administrador', 'order' => 2, 'color_theme_id' => 2],
            ['name' => 'Suporte', 'order' => 3, 'color_theme_id' => 7],
            ['name' => 'Planejamento', 'order' => 4, 'color_theme_id' => 7],
            ['name' => 'Logistica', 'order' => 5, 'color_theme_id' => 3],
            ['name' => 'E-commerce', 'order' => 6, 'color_theme_id' => 7],
            ['name' => 'Departamento Pessoal', 'order' => 7, 'color_theme_id' => 7],
            ['name' => 'Departamento Pessoal Nível 1', 'order' => 8, 'color_theme_id' => 7],
            ['name' => 'Financeiro', 'order' => 9, 'color_theme_id' => 7],
            ['name' => 'Financeiro Nível 1', 'order' => 10, 'color_theme_id' => 7],
            ['name' => 'Logistica Nível 1', 'order' => 11, 'color_theme_id' => 3],
            ['name' => 'Qualidade', 'order' => 12, 'color_theme_id' => 3],
            ['name' => 'Marketing', 'order' => 13, 'color_theme_id' => 3],
            ['name' => 'Operações', 'order' => 14, 'color_theme_id' => 1],
            ['name' => 'Tesouraria', 'order' => 15, 'color_theme_id' => 7],
            ['name' => 'Supervisão Comercial', 'order' => 16, 'color_theme_id' => 1],
            ['name' => 'Pessoas & Cultura', 'order' => 17, 'color_theme_id' => 8],
            ['name' => 'Loja', 'order' => 18, 'color_theme_id' => 8],
            ['name' => 'Gerencial', 'order' => 19, 'color_theme_id' => 8],
            ['name' => 'Estoque', 'order' => 20, 'color_theme_id' => 3],
            ['name' => 'Treinamento', 'order' => 21, 'color_theme_id' => 1],
            ['name' => 'Motorista', 'order' => 22, 'color_theme_id' => 3],
            ['name' => 'Candidato', 'order' => 23, 'color_theme_id' => 7],
            ['name' => 'Contábil', 'order' => 24, 'color_theme_id' => 8],
        ];

        foreach ($accessLevels as $level) {
            DB::table('access_levels')->updateOrInsert(
                ['name' => $level['name']],
                [
                    'order' => $level['order'],
                    'color_theme_id' => $level['color_theme_id'],
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }
    }
}
