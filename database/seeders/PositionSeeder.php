<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            ['id' => 1, 'name' => 'Consultor(a) de Vendas', 'level' => 'Consultor(a) de Vendas', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 2, 'name' => 'Gerente', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 3, 'name' => 'Caixa', 'level' => 'Operador(a) de Caixa', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 4, 'name' => 'Estoquista', 'level' => 'Estoque', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 5, 'name' => 'Analista Fiscal', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 6, 'name' => 'Assistente Comercial', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 7, 'name' => 'Gestor', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 8, 'name' => 'Estagiario', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 9, 'name' => 'Analista Financeiro', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 10, 'name' => 'Analista de Planejamento de Vendas', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 11, 'name' => 'Analista de T.I', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 12, 'name' => 'Supervisor(a) Comercial', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 13, 'name' => 'Consultor(a) VR', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 14, 'name' => 'Diretor(a)', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 15, 'name' => 'Analista de Marketing', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 16, 'name' => 'Coordenador(a)', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 17, 'name' => 'Analista Contábil', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 18, 'name' => 'Contador(a)', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 19, 'name' => 'Assistente de Marketing', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 20, 'name' => 'Diretor Adm. Financeiro', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 22, 'name' => 'Diretor(a) Comercial', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 23, 'name' => 'Gerente de Loja (vendas)', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 25, 'name' => 'Supervisor(a) de Facilities', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 26, 'name' => 'Supervisor(a) Administrativo', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 27, 'name' => 'Gerente de E-Commerce', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 28, 'name' => 'Supervisor(a) de Logistica', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 29, 'name' => 'Controller', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 30, 'name' => 'Gerente de Pessoas & Cultura', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 31, 'name' => 'Gerente de Marketing', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 32, 'name' => 'Coordenador(a) Fiscal', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 33, 'name' => 'Supervisor(a) Contábil', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 34, 'name' => 'Supervisor(a) de TI', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 35, 'name' => 'Supervisor(a) de Departamento Pessoal', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 36, 'name' => 'Gerente Comercial', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 40, 'name' => 'Porteiro', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 41, 'name' => 'Auxiliar de Vendas', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 42, 'name' => 'Segurança', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 43, 'name' => 'Copeira', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 46, 'name' => 'Atendente de SAC', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 47, 'name' => 'Auxiliar de E-Commerce', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 48, 'name' => 'Auxiliar de Estoque', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 49, 'name' => 'Encarregado de Estoque CD', 'level' => 'Liderança', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 50, 'name' => 'Estoquista CD', 'level' => 'Estoque', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 52, 'name' => 'Ajudante de Motorista', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 53, 'name' => 'Auxiliar de Logistica', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 54, 'name' => 'Estagiario(a) de Logistica', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 55, 'name' => 'Assistente de E-Commerce', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 56, 'name' => 'Analista de Planejamento Logístico', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 57, 'name' => 'Analista de Tráfego', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 58, 'name' => 'Analista de Qualidade', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 59, 'name' => 'Analista de Pessoas & Cultura', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 60, 'name' => 'Secretaria Executiva', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 61, 'name' => 'Assistente Fiscal', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 62, 'name' => 'Auxiliar de TI', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 63, 'name' => 'Assistente de Logistica', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 64, 'name' => 'Auxiliar de Departamento Pessoal', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 65, 'name' => 'Assistente de Pessoas & Cultura', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 66, 'name' => 'Assistente Financeiro', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 68, 'name' => 'Analista Administrativo', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 69, 'name' => 'Motorista', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 70, 'name' => 'Continuo', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 71, 'name' => 'Aprendiz - Estoque', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 72, 'name' => 'Aprendiz - Vendas', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 73, 'name' => 'Aprendiz - Caixa', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 76, 'name' => 'Subgerente', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 77, 'name' => 'Aprendiz - Administrativo', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 78, 'name' => 'Assistente Digital', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 79, 'name' => 'Assistente de Facilities', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 80, 'name' => 'Analista de Facilities', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 81, 'name' => 'Especialista em Dados', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 82, 'name' => 'Analista de E-Commerce', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 83, 'name' => 'Analista de Dados', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 84, 'name' => 'Auxiliar de Escritório', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 85, 'name' => 'Digitador(a)', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 86, 'name' => 'Auxiliar Administrativo', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 87, 'name' => 'Coordenador(a) de Logistica', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 88, 'name' => 'Analista de Departamento Pessoal', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 89, 'name' => 'Supervisor(a) Controladoria', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 90, 'name' => 'Supervisor(a) Financeiro', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 91, 'name' => 'Supervisor(a) Processos e Projetos', 'level' => 'Liderança', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 92, 'name' => 'Caixa - Volante', 'level' => 'Operador(a) de Caixa', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 93, 'name' => 'Estoquista - Volante', 'level' => 'Estoque', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 94, 'name' => 'Assistente DP', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 95, 'name' => 'Especialista de Controladoria', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 97, 'name' => 'Designer De E-commerce', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 98, 'name' => 'Designer Grafico JR', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 99, 'name' => 'Analista Administrativo JR', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
            ['id' => 100, 'name' => 'Aprendiz de Marketing', 'level' => 'Corporativo', 'level_category_id' => 3, 'status_id' => 1],
            ['id' => 101, 'name' => 'Analista Financeiro JR', 'level' => 'Corporativo', 'level_category_id' => 2, 'status_id' => 1],
        ];

        foreach ($positions as $position) {
            DB::table('positions')->updateOrInsert(
                ['id' => $position['id']],
                array_merge($position, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }
    }
}
