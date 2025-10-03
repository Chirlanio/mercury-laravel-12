<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TypeMoviment;
use Illuminate\Support\Facades\DB;

class TypeMovimentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'id' => 1,
                'name' => 'Admissão',
                'description' => 'Contratação inicial do funcionário',
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Promoção',
                'description' => 'Promoção para cargo superior',
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Mudança de Cargo',
                'description' => 'Alteração de cargo/função',
                'is_active' => true,
            ],
            [
                'id' => 4,
                'name' => 'Transferência',
                'description' => 'Transferência de loja/unidade',
                'is_active' => true,
            ],
            [
                'id' => 5,
                'name' => 'Demissão',
                'description' => 'Encerramento do contrato',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            DB::table('type_moviments')->updateOrInsert(
                ['id' => $type['id']],
                $type
            );
        }
    }
}
