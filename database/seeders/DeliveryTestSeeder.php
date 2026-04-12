<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para testar roteirização e otimização de rotas.
 * Cria 1 loja + 1 motorista + 12 entregas em Fortaleza com coordenadas reais.
 *
 * Uso: php artisan db:seed --class=DeliveryTestSeeder
 *   ou: php artisan tenants:run db:seed --option="class=Database\Seeders\DeliveryTestSeeder"
 */
class DeliveryTestSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            $this->command->error('Nenhum usuário encontrado. Execute os seeders base primeiro.');

            return;
        }

        // Dependências: network, manager (necessários para stores NOT NULL FKs)
        $networkId = DB::table('networks')->where('nome', 'Operacional')->value('id');
        if (! $networkId) {
            $networkId = DB::table('networks')->insertGetId([
                'nome' => 'Operacional',
                'type' => 'admin',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $managerId = DB::table('managers')->where('name', 'Gerente Teste')->value('id');
        if (! $managerId) {
            $managerId = DB::table('managers')->insertGetId([
                'name' => 'Gerente Teste',
                'email' => 'gerente@teste.com',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Criar loja CD (se não existir) — necessária como FK de deliveries
        $store = DB::table('stores')->where('code', 'Z443')->first();
        if (! $store) {
            DB::table('stores')->insert([
                'code' => 'Z443',
                'name' => 'CD - Meia Sola',
                'cnpj' => '11739570003482',
                'company_name' => 'MEIA SOLA ACESSORIOS DE MODA LTDA',
                'state_registration' => '062652311',
                'address' => 'AV DOM MANUEL, 621 CENTRO FORTALEZA - CE 60060090',
                'network_id' => $networkId,
                'manager_id' => $managerId,
                'store_order' => 1,
                'network_order' => 1,
                'supervisor_id' => $managerId,
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info('Loja CD - Meia Sola (Z443) criada.');
        }

        // Criar motorista de teste (se não existir)
        $driver = Driver::firstOrCreate(
            ['name' => 'João Motorista'],
            [
                'cnh' => '12345678900',
                'cnh_category' => 'B',
                'phone' => '85999001122',
                'is_active' => true,
                'user_id' => $user->id,
            ]
        );

        $this->command->info("Motorista: {$driver->name} (ID: {$driver->id})");

        // Entregas em bairros de Fortaleza com coordenadas reais
        $deliveries = [
            [
                'client_name' => 'Ana Paula Silva',
                'address' => 'Av. Beira Mar, 3150',
                'neighborhood' => 'Meireles',
                'latitude' => -3.7241,
                'longitude' => -38.4896,
            ],
            [
                'client_name' => 'Carlos Eduardo Santos',
                'address' => 'Rua Frederico Borges, 480',
                'neighborhood' => 'Varjota',
                'latitude' => -3.7310,
                'longitude' => -38.4925,
            ],
            [
                'client_name' => 'Maria José Oliveira',
                'address' => 'Av. Dom Luís, 1200',
                'neighborhood' => 'Aldeota',
                'latitude' => -3.7351,
                'longitude' => -38.5012,
            ],
            [
                'client_name' => 'Pedro Henrique Lima',
                'address' => 'Rua Desembargador Moreira, 900',
                'neighborhood' => 'Aldeota',
                'latitude' => -3.7370,
                'longitude' => -38.5089,
            ],
            [
                'client_name' => 'Juliana Costa Ferreira',
                'address' => 'Av. Washington Soares, 85',
                'neighborhood' => 'Edson Queiroz',
                'latitude' => -3.7725,
                'longitude' => -38.4779,
            ],
            [
                'client_name' => 'Roberto Alves Moreira',
                'address' => 'Rua Lauro Nogueira, 1500',
                'neighborhood' => 'Papicu',
                'latitude' => -3.7410,
                'longitude' => -38.4830,
            ],
            [
                'client_name' => 'Fernanda Souza Rodrigues',
                'address' => 'Av. Santos Dumont, 3131',
                'neighborhood' => 'Aldeota',
                'latitude' => -3.7415,
                'longitude' => -38.5100,
            ],
            [
                'client_name' => 'Lucas Mendes Barbosa',
                'address' => 'Rua Tibúrcio Cavalcante, 2700',
                'neighborhood' => 'Dionísio Torres',
                'latitude' => -3.7457,
                'longitude' => -38.5077,
            ],
            [
                'client_name' => 'Camila Rocha Pereira',
                'address' => 'Av. Barão de Studart, 1700',
                'neighborhood' => 'Aldeota',
                'latitude' => -3.7382,
                'longitude' => -38.5168,
            ],
            [
                'client_name' => 'Thiago Martins Pinto',
                'address' => 'Rua Padre Valdevino, 900',
                'neighborhood' => 'Centro',
                'latitude' => -3.7350,
                'longitude' => -38.5260,
            ],
            [
                'client_name' => 'Patrícia Lima Neves',
                'address' => 'Av. Abolição, 2500',
                'neighborhood' => 'Mucuripe',
                'latitude' => -3.7230,
                'longitude' => -38.4970,
            ],
            [
                'client_name' => 'Rafael Gomes Teixeira',
                'address' => 'Rua Canuto de Aguiar, 600',
                'neighborhood' => 'Meireles',
                'latitude' => -3.7280,
                'longitude' => -38.5050,
            ],
        ];

        $created = 0;
        foreach ($deliveries as $index => $data) {
            Delivery::create([
                'store_id' => 'Z443', // CD - Meia Sola
                'client_name' => $data['client_name'],
                'invoice_number' => 'NF-TEST-'.str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'address' => $data['address'],
                'neighborhood' => $data['neighborhood'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'geocoded_at' => now(),
                'contact_phone' => '8599'.rand(1000000, 9999999),
                'sale_value' => rand(80, 500) + (rand(0, 99) / 100),
                'payment_method' => ['PIX', 'credit_card', 'debit_card', 'cash'][rand(0, 3)],
                'installments' => rand(1, 6),
                'products_qty' => rand(1, 5),
                'status' => Delivery::STATUS_REQUESTED,
                'created_by_user_id' => $user->id,
            ]);
            $created++;
        }

        $this->command->info("Criadas {$created} entregas com coordenadas em Fortaleza.");
        $this->command->info('');
        $this->command->info('=== COMO TESTAR ===');
        $this->command->info('');
        $this->command->info('1. Acesse /delivery-routes (Rotas de Entrega)');
        $this->command->info('2. Clique em "Nova Rota"');
        $this->command->info('3. Selecione o motorista e a data');
        $this->command->info('4. Selecione 4+ entregas na lista');
        $this->command->info('5. Clique em "Otimizar Rota" — o mapa aparece com a sequência otimizada');
        $this->command->info('6. Clique "Criar Rota" para salvar');
        $this->command->info('7. No modal de detalhes, veja o mapa com markers coloridos');
        $this->command->info('8. Inicie a rota e acesse /driver-dashboard para ver o painel do motorista com mapa');
    }
}
