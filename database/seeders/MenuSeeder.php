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
            ['name' => 'Home', 'icon' => 'fas fa-home', 'order' => 1, 'is_active' => true],
            ['name' => 'Usuário', 'icon' => 'fas fa-user', 'order' => 2, 'is_active' => true],
            ['name' => 'Produto', 'icon' => 'fas fa-shopping-cart', 'order' => 3, 'is_active' => true],
            ['name' => 'Planejamento', 'icon' => 'fa-solid fa-diagram-project', 'order' => 4, 'is_active' => true],
            ['name' => 'Financeiro', 'icon' => 'fas fa-credit-card', 'order' => 5, 'is_active' => true],
            ['name' => 'Ativo Fixo', 'icon' => 'fa-solid fa-file-signature', 'order' => 6, 'is_active' => true],
            ['name' => 'Comercial', 'icon' => 'fa-solid fa-money-bill-wave', 'order' => 7, 'is_active' => true],
            ['name' => 'Delivery', 'icon' => 'fas fa-shipping-fast', 'order' => 8, 'is_active' => true],
            ['name' => 'Rotas', 'icon' => 'fa-solid fa-map-location-dot', 'order' => 9, 'is_active' => true],
            ['name' => 'E-commerce', 'icon' => 'fa-solid fa-store', 'order' => 10, 'is_active' => true],
            ['name' => 'Dashboard\'s', 'icon' => 'fas fa-chart-pie', 'order' => 11, 'is_active' => true],
            ['name' => 'Qualidade', 'icon' => 'fa-solid fa-industry', 'order' => 12, 'is_active' => true],
            ['name' => 'Pessoas & Cultura', 'icon' => 'fas fa-users', 'order' => 13, 'is_active' => true],
            ['name' => 'Departamento Pessoal', 'icon' => 'fa-solid fa-address-card', 'order' => 14, 'is_active' => true],
            ['name' => 'Escola Digital', 'icon' => 'fa-solid fa-video', 'order' => 15, 'is_active' => true],
            ['name' => 'Movidesk', 'icon' => 'fas fa-headset', 'order' => 16, 'is_active' => true],
            ['name' => 'Biblioteca de Processos', 'icon' => 'fa-solid fa-landmark', 'order' => 17, 'is_active' => true],
            ['name' => 'FAQ\'s', 'icon' => 'fas fa-question-circle', 'order' => 18, 'is_active' => true],
            ['name' => 'Configurações', 'icon' => 'fas fa-cogs', 'order' => 19, 'is_active' => true],
            ['name' => 'Sair', 'icon' => 'fas fa-sign-out-alt', 'order' => 20, 'is_active' => true],
        ];

        foreach ($menus as $menu) {
            DB::table('menus')->insert([
                'name' => $menu['name'],
                'icon' => $menu['icon'],
                'order' => $menu['order'],
                'is_active' => $menu['is_active'],
                'created_at' => $now,
                'updated_at' => null,
            ]);
        }
    }
}
