<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmploymentRelationshipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $relationships = [
            ['name' => 'Colaborador efetivo'],
            ['name' => 'Colaborador temporário'],
            ['name' => 'Estagiário'],
            ['name' => 'Jovem aprendiz'],
        ];

        foreach ($relationships as $relationship) {
            DB::table('employment_relationships')->insert([
                'name' => $relationship['name'],
                'created_at' => $now,
                'updated_at' => null,
            ]);
        }
    }
}
