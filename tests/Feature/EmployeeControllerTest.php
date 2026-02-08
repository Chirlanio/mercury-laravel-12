<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->createTestStore('Z999');
    }

    // ===== INDEX =====

    public function test_employees_index_requires_authentication(): void
    {
        $response = $this->get('/employees');

        $response->assertRedirect('/login');
    }

    public function test_employees_index_is_displayed_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/employees');

        $response->assertOk();
    }

    public function test_employees_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/employees');

        $response->assertStatus(403);
    }

    public function test_employees_can_be_searched_by_name(): void
    {
        $this->createTestEmployee(['name' => 'JOAO SILVA', 'cpf' => '11111111111']);
        $this->createTestEmployee(['name' => 'MARIA SANTOS', 'cpf' => '22222222222']);

        $response = $this->actingAs($this->adminUser)->get('/employees?search=JOAO');

        $response->assertOk();
    }

    public function test_employees_can_be_filtered_by_store(): void
    {
        $this->createTestEmployee(['store_id' => 'Z999', 'cpf' => '11111111111']);

        $response = $this->actingAs($this->adminUser)->get('/employees?store=Z999');

        $response->assertOk();
    }

    public function test_employees_can_be_filtered_by_status(): void
    {
        $this->createTestEmployee(['status_id' => 2, 'cpf' => '11111111111']);

        $response = $this->actingAs($this->adminUser)->get('/employees?status=2');

        $response->assertOk();
    }

    // ===== CRUD =====

    public function test_employee_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'name' => 'Novo Funcionario',
            'cpf' => '99988877766',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'name' => 'NOVO FUNCIONARIO',
            'cpf' => '99988877766',
        ]);
    }

    public function test_employee_creation_requires_name(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'name' => '',
            'cpf' => '99988877766',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_employee_creation_requires_unique_cpf(): void
    {
        $this->createTestEmployee(['cpf' => '12345678901']);

        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'name' => 'Duplicate CPF',
            'cpf' => '12345678901',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertSessionHasErrors('cpf');
    }

    public function test_employee_name_is_uppercased_on_create(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'name' => 'joao da silva',
            'cpf' => '99988877766',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['name' => 'JOAO DA SILVA']);
    }

    public function test_employee_creation_auto_creates_admission_contract(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'name' => 'Contrato Auto',
            'cpf' => '99988877766',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertRedirect();

        $employee = DB::table('employees')->where('cpf', '99988877766')->first();
        $this->assertDatabaseHas('employment_contracts', [
            'employee_id' => $employee->id,
            'movement_type_id' => 1,
            'is_active' => true,
        ]);
    }

    public function test_employee_show_returns_json(): void
    {
        $employeeId = $this->createTestEmployee();

        $response = $this->actingAs($this->adminUser)->getJson("/employees/{$employeeId}");

        $response->assertOk();
        $response->assertJsonStructure(['employee' => ['id', 'name', 'cpf']]);
    }

    public function test_employee_edit_returns_json(): void
    {
        $employeeId = $this->createTestEmployee();

        $response = $this->actingAs($this->adminUser)->getJson("/employees/{$employeeId}/edit");

        $response->assertOk();
        $response->assertJsonStructure(['employee' => ['id', 'name', 'cpf']]);
    }

    public function test_employee_can_be_updated(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '11111111111']);

        $response = $this->actingAs($this->adminUser)->put("/employees/{$employeeId}", [
            'name' => 'Updated Name',
            'cpf' => '11111111111',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'id' => $employeeId,
            'name' => 'UPDATED NAME',
        ]);
    }

    public function test_employee_update_rejects_duplicate_cpf(): void
    {
        $employeeId1 = $this->createTestEmployee(['cpf' => '11111111111', 'name' => 'FIRST']);
        $employeeId2 = $this->createTestEmployee(['cpf' => '22222222222', 'name' => 'SECOND']);

        $response = $this->actingAs($this->adminUser)->put("/employees/{$employeeId2}", [
            'name' => 'Second Employee',
            'cpf' => '11111111111',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertSessionHasErrors('cpf');
    }

    public function test_employee_can_be_deleted(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '33333333333']);

        $response = $this->actingAs($this->adminUser)->delete("/employees/{$employeeId}");

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseMissing('employees', ['id' => $employeeId]);
    }

    public function test_employee_create_blocked_without_permission(): void
    {
        $response = $this->actingAs($this->supportUser)->post('/employees', [
            'name' => 'Should Fail',
            'cpf' => '99988877766',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertStatus(403);
    }

    public function test_employee_update_blocked_without_permission(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '44444444444']);

        $response = $this->actingAs($this->supportUser)->put("/employees/{$employeeId}", [
            'name' => 'Updated',
            'cpf' => '44444444444',
            'admission_date' => '2024-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
        ]);

        $response->assertStatus(403);
    }

    // ===== CONTRACTS =====

    public function test_contract_can_be_created(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '55555555555']);

        // Create the initial admission contract
        DB::table('employment_contracts')->insert([
            'employee_id' => $employeeId,
            'position_id' => 1,
            'movement_type_id' => 1,
            'store_id' => 'Z999',
            'start_date' => now()->subYear(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->postJson("/employees/{$employeeId}/contracts", [
            'position_id' => 2,
            'movement_type_id' => 2,
            'store_id' => 'Z999',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'contract']);
    }

    public function test_contract_validates_required_fields(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '55555555555']);

        $response = $this->actingAs($this->adminUser)->postJson("/employees/{$employeeId}/contracts", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['position_id', 'movement_type_id', 'store_id', 'start_date']);
    }

    public function test_contract_deactivates_previous_active(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '55555555555']);

        $contractId = DB::table('employment_contracts')->insertGetId([
            'employee_id' => $employeeId,
            'position_id' => 1,
            'movement_type_id' => 1,
            'store_id' => 'Z999',
            'start_date' => now()->subYear(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->adminUser)->postJson("/employees/{$employeeId}/contracts", [
            'position_id' => 2,
            'movement_type_id' => 2,
            'store_id' => 'Z999',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('employment_contracts', [
            'id' => $contractId,
            'is_active' => false,
        ]);
    }

    public function test_dismissal_contract_sets_employee_inactive(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '55555555555', 'status_id' => 2]);

        DB::table('employment_contracts')->insert([
            'employee_id' => $employeeId,
            'position_id' => 1,
            'movement_type_id' => 1,
            'store_id' => 'Z999',
            'start_date' => now()->subYear(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->adminUser)->postJson("/employees/{$employeeId}/contracts", [
            'position_id' => 1,
            'movement_type_id' => 5, // Demissão
            'store_id' => 'Z999',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employeeId,
            'status_id' => 3, // Inativo
        ]);
    }

    public function test_admission_contract_cannot_be_deleted(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '55555555555']);

        $contractId = DB::table('employment_contracts')->insertGetId([
            'employee_id' => $employeeId,
            'position_id' => 1,
            'movement_type_id' => 1,
            'store_id' => 'Z999',
            'start_date' => now()->subYear(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson("/employees/{$employeeId}/contracts/{$contractId}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('employment_contracts', ['id' => $contractId]);
    }

    public function test_non_admission_contract_can_be_deleted(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '55555555555']);

        // Create admission contract (oldest)
        DB::table('employment_contracts')->insert([
            'employee_id' => $employeeId,
            'position_id' => 1,
            'movement_type_id' => 1,
            'store_id' => 'Z999',
            'start_date' => now()->subYears(2),
            'is_active' => false,
            'end_date' => now()->subYear(),
            'created_at' => now()->subYears(2),
            'updated_at' => now()->subYears(2),
        ]);

        // Create promotion contract (newer)
        $promotionId = DB::table('employment_contracts')->insertGetId([
            'employee_id' => $employeeId,
            'position_id' => 2,
            'movement_type_id' => 2,
            'store_id' => 'Z999',
            'start_date' => now()->subYear(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson("/employees/{$employeeId}/contracts/{$promotionId}");

        $response->assertOk();
        $this->assertDatabaseMissing('employment_contracts', ['id' => $promotionId]);
    }

    public function test_contract_can_be_reactivated(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '55555555555']);

        $contractId = DB::table('employment_contracts')->insertGetId([
            'employee_id' => $employeeId,
            'position_id' => 1,
            'movement_type_id' => 1,
            'store_id' => 'Z999',
            'start_date' => now()->subYear(),
            'end_date' => now()->subMonth(),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->postJson("/employees/{$employeeId}/contracts/{$contractId}/reactivate");

        $response->assertOk();
        $this->assertDatabaseHas('employment_contracts', [
            'id' => $contractId,
            'is_active' => true,
            'end_date' => null,
        ]);
    }

    // ===== EVENTS =====

    public function test_events_returns_json(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '66666666666']);

        $response = $this->actingAs($this->adminUser)->getJson("/employees/{$employeeId}/events");

        $response->assertOk();
        $response->assertJsonStructure(['events', 'event_types']);
    }

    public function test_event_can_be_created(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '66666666666']);

        $response = $this->actingAs($this->adminUser)->postJson("/employees/{$employeeId}/events", [
            'event_type_id' => 3, // Falta
            'start_date' => now()->format('Y-m-d'),
            'notes' => 'Faltou sem justificativa',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employee_events', [
            'employee_id' => $employeeId,
            'event_type_id' => 3,
        ]);
    }

    public function test_event_can_be_deleted(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '66666666666']);

        $eventId = DB::table('employee_events')->insertGetId([
            'employee_id' => $employeeId,
            'event_type_id' => 3,
            'start_date' => now(),
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson("/employees/{$employeeId}/events/{$eventId}");

        $response->assertOk();
        $this->assertDatabaseMissing('employee_events', ['id' => $eventId]);
    }

    // ===== HISTORY =====

    public function test_employee_history_returns_json(): void
    {
        $employeeId = $this->createTestEmployee(['cpf' => '77777777777']);

        DB::table('employment_contracts')->insert([
            'employee_id' => $employeeId,
            'position_id' => 1,
            'movement_type_id' => 1,
            'store_id' => 'Z999',
            'start_date' => now()->subYear(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->getJson("/employees/{$employeeId}/history");

        $response->assertOk();
        $response->assertJsonStructure(['employee', 'histories', 'contracts']);
    }
}
