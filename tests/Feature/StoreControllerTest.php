<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Network;
use App\Models\Position;
use App\Models\Status;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoreControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required related data directly (avoid seeders with MySQL-specific queries)
        $this->createColorThemes();
        $this->createAccessLevels();
        $this->createNetworks();
        $this->createStatuses();
        $this->createPositions();

        // Create admin user with proper role
        $this->adminUser = User::factory()->create([
            'role' => Role::ADMIN->value,
            'access_level_id' => 1,
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
        ]);
    }

    private function createNetworks(): void
    {
        $networks = [
            ['id' => 1, 'nome' => 'Arezzo', 'type' => 'comercial', 'active' => true],
            ['id' => 2, 'nome' => 'Anacapri', 'type' => 'comercial', 'active' => true],
            ['id' => 3, 'nome' => 'Meia Sola', 'type' => 'comercial', 'active' => true],
            ['id' => 4, 'nome' => 'Schutz', 'type' => 'comercial', 'active' => true],
            ['id' => 5, 'nome' => 'Outlet', 'type' => 'comercial', 'active' => true],
            ['id' => 6, 'nome' => 'E-Commerce', 'type' => 'comercial', 'active' => true],
            ['id' => 7, 'nome' => 'Operacional', 'type' => 'admin', 'active' => true],
            ['id' => 8, 'nome' => 'Arezzo Brizza', 'type' => 'comercial', 'active' => true],
        ];

        foreach ($networks as $network) {
            Network::create($network);
        }
    }

    private function createColorThemes(): void
    {
        $themes = [
            ['id' => 1, 'name' => 'Green', 'color_class' => 'bg-green-500'],
            ['id' => 2, 'name' => 'Red', 'color_class' => 'bg-red-500'],
            ['id' => 3, 'name' => 'Blue', 'color_class' => 'bg-blue-500'],
        ];

        foreach ($themes as $theme) {
            DB::table('color_themes')->insert(array_merge($theme, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createAccessLevels(): void
    {
        $levels = [
            ['id' => 1, 'name' => 'Admin', 'order' => 1, 'color_theme_id' => 1],
            ['id' => 2, 'name' => 'Gerente', 'order' => 2, 'color_theme_id' => 1],
            ['id' => 3, 'name' => 'Supervisor', 'order' => 3, 'color_theme_id' => 2],
            ['id' => 4, 'name' => 'Usuario', 'order' => 4, 'color_theme_id' => 3],
        ];

        foreach ($levels as $level) {
            DB::table('access_levels')->insert(array_merge($level, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createStatuses(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'Ativo', 'color_theme_id' => 1],
            ['id' => 2, 'name' => 'Inativo', 'color_theme_id' => 2],
        ];

        foreach ($statuses as $status) {
            DB::table('statuses')->insert(array_merge($status, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createPositions(): void
    {
        $positions = [
            ['id' => 1, 'name' => 'Vendedor', 'level' => 'Junior', 'level_category_id' => 1, 'status_id' => 1],
            ['id' => 2, 'name' => 'Gerente', 'level' => 'Senior', 'level_category_id' => 1, 'status_id' => 1],
        ];

        foreach ($positions as $position) {
            DB::table('positions')->insert(array_merge($position, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createEmployee(): int
    {
        $employeeId = DB::table('employees')->insertGetId([
            'name' => 'TEST EMPLOYEE',
            'short_name' => 'TEST',
            'cpf' => '12345678901',
            'admission_date' => now()->subYear(),
            'birth_date' => now()->subYears(30),
            'position_id' => 1,
            'store_id' => 'Z999',
            'education_level_id' => 1,
            'gender_id' => 1,
            'area_id' => 1,
            'is_pcd' => false,
            'is_apprentice' => false,
            'level' => 'Junior',
            'status_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $employeeId;
    }

    public function test_stores_index_page_is_displayed_for_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->adminUser)
            ->get('/stores');

        $response->assertOk();
    }

    public function test_stores_index_requires_authentication(): void
    {
        $response = $this->get('/stores');

        $response->assertRedirect('/login');
    }

    public function test_store_can_be_created(): void
    {
        // Create an employee to use as manager/supervisor
        $employeeId = $this->createEmployee();

        $storeData = [
            'code' => 'Z998',
            'name' => 'Test Store',
            'cnpj' => '12.345.678/0001-90',
            'company_name' => 'TEST COMPANY LTDA',
            'state_registration' => '123456789',
            'address' => 'Test Address, 123',
            'network_id' => 1,
            'manager_id' => $employeeId,
            'supervisor_id' => $employeeId,
            'store_order' => 1,
            'network_order' => 1,
            'status_id' => 1,
        ];

        $response = $this
            ->actingAs($this->adminUser)
            ->post('/stores', $storeData);

        $response->assertRedirect('/stores');

        $this->assertDatabaseHas('stores', [
            'code' => 'Z998',
            'name' => 'TEST STORE',
        ]);
    }

    public function test_store_requires_valid_code(): void
    {
        $employeeId = $this->createEmployee();

        $storeData = [
            'code' => '', // Empty code
            'name' => 'Test Store',
            'cnpj' => '12.345.678/0001-90',
            'company_name' => 'TEST COMPANY LTDA',
            'address' => 'Test Address, 123',
            'network_id' => 1,
            'manager_id' => $employeeId,
            'supervisor_id' => $employeeId,
            'store_order' => 1,
            'network_order' => 1,
        ];

        $response = $this
            ->actingAs($this->adminUser)
            ->post('/stores', $storeData);

        $response->assertSessionHasErrors('code');
    }

    public function test_store_code_must_be_unique(): void
    {
        $employeeId = $this->createEmployee();

        // Create existing store
        $this->createStore('Z100', $employeeId);

        $storeData = [
            'code' => 'Z100', // Duplicate code
            'name' => 'Test Store',
            'cnpj' => '12.345.678/0001-90',
            'company_name' => 'TEST COMPANY LTDA',
            'address' => 'Test Address, 123',
            'network_id' => 1,
            'manager_id' => $employeeId,
            'supervisor_id' => $employeeId,
            'store_order' => 1,
            'network_order' => 1,
        ];

        $response = $this
            ->actingAs($this->adminUser)
            ->post('/stores', $storeData);

        $response->assertSessionHasErrors('code');
    }

    public function test_store_can_be_viewed(): void
    {
        $employeeId = $this->createEmployee();
        $storeId = $this->createStore('Z101', $employeeId);

        $response = $this
            ->actingAs($this->adminUser)
            ->get("/stores/{$storeId}");

        $response->assertOk();
    }

    public function test_store_can_be_updated(): void
    {
        $employeeId = $this->createEmployee();
        $storeId = $this->createStore('Z102', $employeeId);
        $store = Store::find($storeId);

        $updatedData = [
            'code' => $store->code,
            'name' => 'Updated Store Name',
            'cnpj' => $store->cnpj,
            'company_name' => $store->company_name,
            'address' => $store->address,
            'network_id' => $store->network_id,
            'manager_id' => $employeeId,
            'supervisor_id' => $employeeId,
            'store_order' => $store->store_order,
            'network_order' => $store->network_order,
        ];

        $response = $this
            ->actingAs($this->adminUser)
            ->put("/stores/{$storeId}", $updatedData);

        $response->assertRedirect('/stores');

        $this->assertDatabaseHas('stores', [
            'id' => $storeId,
            'name' => 'UPDATED STORE NAME',
        ]);
    }

    public function test_store_can_be_deleted(): void
    {
        $employeeId = $this->createEmployee();
        $storeId = $this->createStore('Z103', $employeeId);

        $response = $this
            ->actingAs($this->adminUser)
            ->delete("/stores/{$storeId}");

        $response->assertRedirect('/stores');

        $this->assertDatabaseMissing('stores', [
            'id' => $storeId,
        ]);
    }

    public function test_store_with_employees_cannot_be_deleted(): void
    {
        $employeeId = $this->createEmployee();
        $storeId = $this->createStore('Z104', $employeeId);
        $store = Store::find($storeId);

        // Update the employee to belong to this store
        DB::table('employees')->where('id', $employeeId)->update(['store_id' => $store->code]);

        $response = $this
            ->actingAs($this->adminUser)
            ->delete("/stores/{$storeId}");

        $response->assertRedirect('/stores');
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('stores', [
            'id' => $storeId,
        ]);
    }

    public function test_store_can_be_activated(): void
    {
        $employeeId = $this->createEmployee();
        $storeId = $this->createStore('Z105', $employeeId, 2); // status_id = 2 (inactive)

        $response = $this
            ->actingAs($this->adminUser)
            ->post("/stores/{$storeId}/activate");

        $response->assertRedirect();

        $this->assertDatabaseHas('stores', [
            'id' => $storeId,
            'status_id' => 1,
        ]);
    }

    public function test_store_can_be_deactivated(): void
    {
        $employeeId = $this->createEmployee();
        $storeId = $this->createStore('Z106', $employeeId, 1); // status_id = 1 (active)

        $response = $this
            ->actingAs($this->adminUser)
            ->post("/stores/{$storeId}/deactivate");

        $response->assertRedirect();

        $this->assertDatabaseHas('stores', [
            'id' => $storeId,
            'status_id' => 2,
        ]);
    }

    public function test_stores_can_be_filtered_by_network(): void
    {
        $employeeId = $this->createEmployee();
        $this->createStore('Z107', $employeeId, 1, 1); // network_id = 1
        $this->createStore('Z108', $employeeId, 1, 2); // network_id = 2

        $response = $this
            ->actingAs($this->adminUser)
            ->get('/stores?network=1');

        $response->assertOk();
    }

    public function test_stores_can_be_searched(): void
    {
        $employeeId = $this->createEmployee();
        $this->createStore('Z109', $employeeId, 1, 1, 'Test Store Alpha');
        $this->createStore('Z110', $employeeId, 1, 1, 'Another Store Beta');

        $response = $this
            ->actingAs($this->adminUser)
            ->get('/stores?search=Alpha');

        $response->assertOk();
    }

    public function test_stores_select_api_returns_active_stores(): void
    {
        $employeeId = $this->createEmployee();
        $this->createStore('Z111', $employeeId, 1); // active
        $this->createStore('Z112', $employeeId, 2); // inactive

        $response = $this
            ->actingAs($this->adminUser)
            ->get('/api/stores/select');

        $response->assertOk();
        $response->assertJsonStructure([
            'stores' => [
                '*' => ['id', 'code', 'name', 'display_name', 'network_id']
            ]
        ]);
    }

    public function test_stores_can_be_reordered(): void
    {
        $employeeId = $this->createEmployee();
        $store1Id = $this->createStore('Z113', $employeeId);
        $store2Id = $this->createStore('Z114', $employeeId);

        // Update store orders manually
        DB::table('stores')->where('id', $store1Id)->update(['store_order' => 1]);
        DB::table('stores')->where('id', $store2Id)->update(['store_order' => 2]);

        $response = $this
            ->actingAs($this->adminUser)
            ->post('/stores/reorder', [
                'stores' => [
                    ['id' => $store1Id, 'store_order' => 2],
                    ['id' => $store2Id, 'store_order' => 1],
                ]
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('stores', [
            'id' => $store1Id,
            'store_order' => 2,
        ]);

        $this->assertDatabaseHas('stores', [
            'id' => $store2Id,
            'store_order' => 1,
        ]);
    }

    private function createStore(string $code, int $employeeId, int $statusId = 1, int $networkId = 1, string $name = 'Test Store'): int
    {
        return DB::table('stores')->insertGetId([
            'code' => $code,
            'name' => strtoupper($name),
            'cnpj' => '12345678000190',
            'company_name' => 'TEST COMPANY LTDA',
            'state_registration' => '123456789',
            'address' => 'TEST ADDRESS 123',
            'network_id' => $networkId,
            'manager_id' => $employeeId,
            'supervisor_id' => $employeeId,
            'store_order' => 1,
            'network_order' => 1,
            'status_id' => $statusId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
