<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PageStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pageStatuses = [
            ['name' => 'Ativo', 'color' => 'success', 'created_at' => '2020-03-23 00:00:00', 'updated_at' => null],
            ['name' => 'Inativo', 'color' => 'danger', 'created_at' => '2020-03-23 00:00:00', 'updated_at' => null],
            ['name' => 'Analise', 'color' => 'primary', 'created_at' => '2020-03-23 00:00:00', 'updated_at' => null],
        ];

        foreach ($pageStatuses as $pageStatus) {
            DB::table('page_statuses')->updateOrInsert(
                ['name' => $pageStatus['name']],
                [
                    'color' => $pageStatus['color'],
                    'created_at' => $pageStatus['created_at'],
                    'updated_at' => $pageStatus['updated_at'],
                ]
            );
        }
    }
}
