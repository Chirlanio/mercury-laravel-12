<?php

namespace Database\Seeders;

use App\Models\Network;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NetworkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $networks = [
            ['nome' => 'AREZZO', 'type' => 'comercial', 'active' => true],
            ['nome' => 'ANACAPRI', 'type' => 'comercial', 'active' => true],
            ['nome' => 'MEIA SOLA', 'type' => 'comercial', 'active' => true],
            ['nome' => 'SCHUTZ', 'type' => 'comercial', 'active' => true],
            ['nome' => 'MS OFF', 'type' => 'comercial', 'active' => true],
            ['nome' => 'E-COMMERCE', 'type' => 'comercial', 'active' => true],
            ['nome' => 'ADMINISTRATIVO', 'type' => 'admin', 'active' => true],
            ['nome' => 'BRIZZA', 'type' => 'comercial', 'active' => false],
            ['nome' => 'GERAL', 'type' => 'comercial', 'active' => true],
        ];

        foreach ($networks as $network) {
            Network::create($network);
        }
    }
}
