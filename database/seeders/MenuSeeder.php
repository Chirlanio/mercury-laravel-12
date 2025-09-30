<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $menus = [
            ['name' => 'Home', 'icon' => 'fas fa-home', 'order' => 1, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Usuário', 'icon' => 'fas fa-user', 'order' => 2, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Produto', 'icon' => 'fas fa-shopping-cart', 'order' => 3, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Planejamento', 'icon' => 'fa-solid fa-diagram-project', 'order' => 4, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Financeiro', 'icon' => 'fas fa-credit-card', 'order' => 5, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Ativo Fixo', 'icon' => 'fa-solid fa-file-signature', 'order' => 6, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Comercial', 'icon' => 'fa-solid fa-money-bill-wave', 'order' => 7, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Delivery', 'icon' => 'fas fa-shipping-fast', 'order' => 8, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Rotas', 'icon' => 'fa-solid fa-map-location-dot', 'order' => 9, 'is_active' => true, 'parent_id' => null],
            ['name' => 'E-commerce', 'icon' => 'fa-solid fa-store', 'order' => 10, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Dashboard\'s', 'icon' => 'fas fa-chart-pie', 'order' => 11, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Qualidade', 'icon' => 'fa-solid fa-industry', 'order' => 12, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Pessoas & Cultura', 'icon' => 'fas fa-users', 'order' => 13, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Departamento Pessoal', 'icon' => 'fa-solid fa-address-card', 'order' => 14, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Escola Digital', 'icon' => 'fa-solid fa-video', 'order' => 15, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Movidesk', 'icon' => 'fas fa-headset', 'order' => 16, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Biblioteca de Processos', 'icon' => 'fa-solid fa-landmark', 'order' => 17, 'is_active' => true, 'parent_id' => null],
            ['name' => 'FAQ\'s', 'icon' => 'fas fa-question-circle', 'order' => 18, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Configurações', 'icon' => 'fas fa-cogs', 'order' => 19, 'is_active' => true, 'parent_id' => null],
            ['name' => 'Sair', 'icon' => 'fas fa-sign-out-alt', 'order' => 20, 'is_active' => true, 'parent_id' => null],
        ];

        foreach ($menus as $menu) {
            DB::table('menus')->updateOrInsert(
                ['name' => $menu['name']],
                [
                    'icon' => $menu['icon'],
                    'order' => $menu['order'],
                    'is_active' => $menu['is_active'],
                    'parent_id' => $menu['parent_id'],
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }

        // Criar submenu "Funcionários" para "Departamento Pessoal"
        $departamentoPessoal = DB::table('menus')->where('name', 'Departamento Pessoal')->first();

        if ($departamentoPessoal) {
            DB::table('menus')->updateOrInsert(
                ['name' => 'Funcionários', 'parent_id' => $departamentoPessoal->id],
                [
                    'icon' => 'fas fa-users',
                    'order' => 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }
    }
}
