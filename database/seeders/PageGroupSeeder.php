<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PageGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $pageGroups = [
            ['name' => 'Listar'],
            ['name' => 'Cadastrar'],
            ['name' => 'Editar'],
            ['name' => 'Apagar'],
            ['name' => 'Visualizar'],
            ['name' => 'Outros'],
            ['name' => 'Acesso'],
            ['name' => 'Pesquisar'],
        ];

        foreach ($pageGroups as $group) {
            DB::table('page_groups')->updateOrInsert(
                ['name' => $group['name']],
                [
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }
    }
}
