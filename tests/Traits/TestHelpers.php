<?php

namespace Tests\Traits;

use App\Enums\Role;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductVariant;
use App\Models\ProductSyncLog;
use App\Models\Supplier;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Illuminate\Support\Facades\DB;

trait TestHelpers
{
    protected User $adminUser;
    protected User $supportUser;
    protected User $regularUser;

    protected function setUpTestData(): void
    {
        $this->createColorThemes();
        $this->createAccessLevels();
        $this->createNetworks();
        $this->createStatuses();
        $this->createPositionLevels();
        $this->createPositions();
        $this->createGenders();
        $this->createEducationLevels();
        $this->createEmployeeStatuses();
        $this->createTypeMoviments();
        $this->createManagers();
        $this->createSectors();
        $this->createPageGroups();
        $this->createPageStatuses();
        $this->createEmployeeEventTypes();
        $this->createEmploymentRelationships();

        $this->adminUser = User::factory()->create([
            'role' => Role::ADMIN->value,
            'access_level_id' => 1,
        ]);

        $this->supportUser = User::factory()->create([
            'role' => Role::SUPPORT->value,
            'access_level_id' => 3,
        ]);

        $this->regularUser = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
        ]);
    }

    private function createColorThemes(): void
    {
        $themes = [
            ['id' => 1, 'name' => 'Green', 'color_class' => 'bg-green-500', 'hex_color' => '#22c55e'],
            ['id' => 2, 'name' => 'Red', 'color_class' => 'bg-red-500', 'hex_color' => '#ef4444'],
            ['id' => 3, 'name' => 'Blue', 'color_class' => 'bg-blue-500', 'hex_color' => '#3b82f6'],
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
            DB::table('networks')->insert(array_merge($network, [
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

    private function createPositionLevels(): void
    {
        $levels = [
            ['id' => 1, 'name' => 'Nivel 1'],
            ['id' => 2, 'name' => 'Nivel 2'],
        ];

        foreach ($levels as $level) {
            DB::table('position_levels')->insert(array_merge($level, [
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

    private function createGenders(): void
    {
        $genders = [
            ['id' => 1, 'description_name' => 'Masculino', 'is_active' => true],
            ['id' => 2, 'description_name' => 'Feminino', 'is_active' => true],
        ];

        foreach ($genders as $gender) {
            DB::table('genders')->insert(array_merge($gender, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createEducationLevels(): void
    {
        $levels = [
            ['id' => 1, 'description_name' => 'Ensino Medio', 'is_active' => true],
            ['id' => 2, 'description_name' => 'Ensino Superior', 'is_active' => true],
            ['id' => 3, 'description_name' => 'Pos-Graduacao', 'is_active' => true],
        ];

        foreach ($levels as $level) {
            DB::table('education_levels')->insert(array_merge($level, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createEmployeeStatuses(): void
    {
        $statuses = [
            ['id' => 1, 'description_name' => 'Pendente', 'color_theme_id' => 3],
            ['id' => 2, 'description_name' => 'Ativo', 'color_theme_id' => 1],
            ['id' => 3, 'description_name' => 'Inativo', 'color_theme_id' => 2],
            ['id' => 4, 'description_name' => 'Ferias', 'color_theme_id' => 3],
            ['id' => 5, 'description_name' => 'Licenca', 'color_theme_id' => 3],
        ];

        foreach ($statuses as $status) {
            DB::table('employee_statuses')->insert(array_merge($status, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createTypeMoviments(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Admissao', 'is_active' => true],
            ['id' => 2, 'name' => 'Promocao', 'is_active' => true],
            ['id' => 3, 'name' => 'Transferencia', 'is_active' => true],
            ['id' => 4, 'name' => 'Alteracao Salarial', 'is_active' => true],
            ['id' => 5, 'name' => 'Demissao', 'is_active' => true],
        ];

        foreach ($types as $type) {
            DB::table('type_moviments')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createManagers(): void
    {
        $managers = [
            ['id' => 1, 'name' => 'Manager Area', 'email' => 'area@test.com', 'is_active' => true],
            ['id' => 2, 'name' => 'Manager Setor', 'email' => 'setor@test.com', 'is_active' => true],
        ];

        foreach ($managers as $manager) {
            DB::table('managers')->insert(array_merge($manager, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createSectors(): void
    {
        $sectors = [
            ['id' => 1, 'sector_name' => 'Comercial', 'is_active' => true, 'area_manager_id' => 1, 'sector_manager_id' => 2],
            ['id' => 2, 'sector_name' => 'Administrativo', 'is_active' => true, 'area_manager_id' => 1, 'sector_manager_id' => 2],
        ];

        foreach ($sectors as $sector) {
            DB::table('sectors')->insert(array_merge($sector, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createPageGroups(): void
    {
        $groups = [
            ['id' => 1, 'name' => 'Sistema'],
            ['id' => 2, 'name' => 'Configuracoes'],
        ];

        foreach ($groups as $group) {
            DB::table('page_groups')->insert(array_merge($group, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createPageStatuses(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'Publicado', 'color' => 'success'],
            ['id' => 2, 'name' => 'Rascunho', 'color' => 'warning'],
        ];

        foreach ($statuses as $status) {
            DB::table('page_statuses')->insert(array_merge($status, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createEmployeeEventTypes(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Ferias', 'is_active' => true, 'requires_date_range' => true, 'requires_single_date' => false, 'requires_document' => false],
            ['id' => 2, 'name' => 'Atestado Medico', 'is_active' => true, 'requires_date_range' => false, 'requires_single_date' => true, 'requires_document' => false],
            ['id' => 3, 'name' => 'Falta', 'is_active' => true, 'requires_date_range' => false, 'requires_single_date' => true, 'requires_document' => false],
        ];

        foreach ($types as $type) {
            DB::table('employee_event_types')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function createEmploymentRelationships(): void
    {
        $relationships = [
            ['id' => 1, 'name' => 'CLT'],
            ['id' => 2, 'name' => 'PJ'],
        ];

        foreach ($relationships as $rel) {
            DB::table('employment_relationships')->insert(array_merge($rel, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function createTestEmployee(array $overrides = []): int
    {
        return DB::table('employees')->insertGetId(array_merge([
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
        ], $overrides));
    }

    protected function createTestPage(array $overrides = []): int
    {
        return DB::table('pages')->insertGetId(array_merge([
            'page_name' => 'Test Page',
            'controller' => 'TestController',
            'method' => 'index',
            'menu_controller' => 'TestController',
            'menu_method' => 'index',
            'notes' => '',
            'route' => '/test',
            'page_group_id' => 1,
            'is_active' => true,
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function createTestWorkSchedule(array $overrides = []): WorkSchedule
    {
        $schedule = WorkSchedule::create(array_merge([
            'name' => 'TEST SCHEDULE ' . uniqid(),
            'description' => 'Test work schedule',
            'weekly_hours' => 44.00,
            'is_active' => true,
            'is_default' => false,
            'created_by_user_id' => $this->adminUser->id ?? null,
            'updated_by_user_id' => $this->adminUser->id ?? null,
        ], $overrides));

        // Create 7 days (Mon-Fri work, Sat-Sun off)
        $dayConfigs = [
            ['day' => 0, 'work' => false],
            ['day' => 1, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60, 'dh' => 8.80],
            ['day' => 2, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60, 'dh' => 8.80],
            ['day' => 3, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60, 'dh' => 8.80],
            ['day' => 4, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60, 'dh' => 8.80],
            ['day' => 5, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60, 'dh' => 8.80],
            ['day' => 6, 'work' => false],
        ];

        foreach ($dayConfigs as $config) {
            WorkScheduleDay::create([
                'work_schedule_id' => $schedule->id,
                'day_of_week' => $config['day'],
                'is_work_day' => $config['work'],
                'entry_time' => $config['work'] ? $config['entry'] : null,
                'exit_time' => $config['work'] ? $config['exit'] : null,
                'break_start' => $config['work'] ? $config['bs'] : null,
                'break_end' => $config['work'] ? $config['be'] : null,
                'break_duration_minutes' => $config['work'] ? $config['bd'] : null,
                'daily_hours' => $config['work'] ? $config['dh'] : 0,
            ]);
        }

        return $schedule;
    }

    protected int $testStoreId = 0;
    protected int $testEmployeeId = 0;

    protected function createTestSale(array $overrides = []): int
    {
        if (!$this->testStoreId) {
            $this->testStoreId = DB::table('stores')->value('id') ?? $this->createTestStore('S001');
        }
        if (!$this->testEmployeeId) {
            $this->testEmployeeId = DB::table('employees')->value('id') ?? $this->createTestEmployee();
        }

        return DB::table('sales')->insertGetId(array_merge([
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-15',
            'total_sales' => 1500.00,
            'qtde_total' => 10,
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function createTestStore(string $code, array $overrides = []): int
    {
        return DB::table('stores')->insertGetId(array_merge([
            'code' => $code,
            'name' => strtoupper('Test Store ' . $code),
            'cnpj' => '12345678000190',
            'company_name' => 'TEST COMPANY LTDA',
            'state_registration' => '123456789',
            'address' => 'TEST ADDRESS 123',
            'network_id' => 1,
            'manager_id' => 1,
            'supervisor_id' => 1,
            'store_order' => 1,
            'network_order' => 1,
            'status_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function createTestProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'reference' => 'REF-' . uniqid(),
            'description' => 'TEST PRODUCT',
            'is_active' => true,
            'sync_locked' => false,
        ], $overrides));
    }

    protected function createTestProductVariant(Product $product, array $overrides = []): ProductVariant
    {
        return ProductVariant::create(array_merge([
            'product_id' => $product->id,
            'barcode' => 'BC-' . uniqid(),
            'size_cigam_code' => null,
            'is_active' => true,
        ], $overrides));
    }

    protected function createTestProductBrand(array $overrides = []): ProductBrand
    {
        return ProductBrand::create(array_merge([
            'cigam_code' => 'BR-' . uniqid(),
            'name' => 'TEST BRAND',
            'is_active' => true,
        ], $overrides));
    }

    protected function createTestSupplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            'codigo_for' => 'SUP-' . uniqid(),
            'razao_social' => 'TEST SUPPLIER LTDA',
            'is_active' => true,
        ], $overrides));
    }

    protected function createTestProductSyncLog(array $overrides = []): ProductSyncLog
    {
        return ProductSyncLog::create(array_merge([
            'sync_type' => 'full',
            'status' => 'completed',
            'total_records' => 100,
            'processed_records' => 100,
            'inserted_records' => 80,
            'updated_records' => 20,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'started_by_user_id' => $this->adminUser->id ?? null,
        ], $overrides));
    }
}
