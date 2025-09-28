<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $managers = [
            ['name' => 'Deborah Costa', 'email' => 'deborah.costa@meiasola.com.br', 'is_active' => true],
            ['name' => 'Aranilson Silva', 'email' => 'aranilson.silva@meiasola.com.br', 'is_active' => false],
            ['name' => 'Jefferson Oliveira', 'email' => 'jefferson.oliveira@meiasola.com.br', 'is_active' => true],
            ['name' => 'Laila Magalhães', 'email' => 'laila.magalhaes@meiasola.com.br', 'is_active' => true],
            ['name' => 'Rodrigo Castelo Branco', 'email' => 'rodrigo.cb@meiasola.com.br', 'is_active' => true],
            ['name' => 'Rômulo Costa', 'email' => 'romulo.costa@meiasola.com.br', 'is_active' => true],
            ['name' => 'Thiago Ricarte Cortez', 'email' => 'thiago.cortez@meiasola.com.br', 'is_active' => false],
            ['name' => 'Karliane Severo', 'email' => 'karliane.severo@meiasola.com.br', 'is_active' => false],
            ['name' => 'Vânia Dantas', 'email' => 'vania.dantas@meiasola.com.br', 'is_active' => false],
            ['name' => 'Marli Andrade', 'email' => 'dp@meiasola.com.br', 'is_active' => true],
            ['name' => 'Rafaele Lopes', 'email' => 'contabil@meiasola.com.br', 'is_active' => false],
            ['name' => 'Marli Andrade', 'email' => 'marli.andrade@meiasola.com.br', 'is_active' => true],
            ['name' => 'Gilmar Nascimento', 'email' => 'planejamento@meiasola.com.br', 'is_active' => false],
            ['name' => 'Felipe Portela', 'email' => 'portela@meiasola.com.br', 'is_active' => false],
            ['name' => 'Recrutamento e Seleção', 'email' => 'recrutamento@meiasola.com.br', 'is_active' => true],
            ['name' => 'Cleber Leite', 'email' => 'cleber.leite@meiasola.com.br', 'is_active' => true],
            ['name' => 'Lylian Macedo', 'email' => 'lylian.macedo@meiasola.com.br', 'is_active' => true],
            ['name' => 'Jessica Andrade', 'email' => 'jessica.andrade@meiasola.com.br', 'is_active' => false],
            ['name' => 'Elizabete Gomes', 'email' => 'elizabete.gomes@meiasola.com.br', 'is_active' => true],
            ['name' => 'Bruna Ramos', 'email' => 'bruna.ramos@meiasola.com.br', 'is_active' => true],
            ['name' => 'Jessica Luersen', 'email' => 'jessica.luersen@meiasola.com.br', 'is_active' => true],
        ];

        foreach ($managers as $manager) {
            DB::table('managers')->insert([
                'name' => $manager['name'],
                'email' => $manager['email'],
                'is_active' => $manager['is_active'],
                'created_at' => $now,
                'updated_at' => null,
            ]);
        }
    }
}
