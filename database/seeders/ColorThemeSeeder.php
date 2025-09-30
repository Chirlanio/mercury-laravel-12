<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ColorThemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $colorThemes = [
            ['name' => 'Azul', 'color_class' => 'primary'],
            ['name' => 'Vermelho', 'color_class' => 'danger'],
            ['name' => 'Laranja', 'color_class' => 'warning'],
            ['name' => 'Preto', 'color_class' => 'dark'],
            ['name' => 'Branco', 'color_class' => 'light'],
            ['name' => 'Cinza', 'color_class' => 'secondary'], // Corrigido: 'secundary' -> 'secondary'
            ['name' => 'Verde', 'color_class' => 'success'],
            ['name' => 'Azul Claro', 'color_class' => 'info'],
        ];

        foreach ($colorThemes as $colorTheme) {
            DB::table('color_themes')->updateOrInsert(
                ['name' => $colorTheme['name']],
                [
                    'color_class' => $colorTheme['color_class'],
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }
    }
}
