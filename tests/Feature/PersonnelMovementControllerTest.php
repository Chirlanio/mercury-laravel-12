<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PersonnelMovement;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PersonnelMovementControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Store $store2;

    protected Employee $employee;

    protected Employee $inactiveEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->store2 = Store::factory()->create(['code' => 'Z425', 'name' => 'Loja Destino']);

        // Use positions created by TestHelpers (id=1 Vendedor, id=2 Gerente)
        $this->employee = Employee::factory()->create([
            'name' => 'João Silva',
            'store_id' => $this->store->code,
            'position_id' => 1,
            'status_id' => 2,
            'area_id' => 1,
        ]);

        $this->inactiveEmployee = Employee::factory()->create([
            'name' => 'Maria Santos',
            'store_id' => $this->store->code,
            'position_id' => 1,
            'status_id' => 3,
            'dismissal_date' => now()->subMonth(),
            'area_id' => 1,
        ]);
    }

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('personnel-movements.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PersonnelMovements/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('personnel-movements.index'));
        $response->assertStatus(403);
    }

    public function test_can_create_dismissal_movement(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('personnel-movements.store'), [
            'type' => 'dismissal',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'last_day_worked' => now()->addDays(10)->format('Y-m-d'),
            'contract_type' => 'clt',
            'dismissal_subtype' => 'company_initiative',
            'early_warning' => 'worked',
            'observation' => 'Teste desligamento',
        ]);

        $response->assertRedirect(route('personnel-movements.index'));
        $this->assertDatabaseHas('personnel_movements', [
            'type' => 'dismissal',
            'employee_id' => $this->employee->id,
            'status' => 'pending',
        ]);
        // Follow-up created automatically
        $movement = PersonnelMovement::latest()->first();
        $this->assertNotNull($movement->followUp);
        $this->assertTrue($movement->followUp->uniform);
        $this->assertTrue($movement->followUp->aso);
    }

    public function test_can_create_promotion_movement(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('personnel-movements.store'), [
            'type' => 'promotion',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'effective_date' => now()->addDays(5)->format('Y-m-d'),
            'new_position_id' => 2,
            'observation' => 'Promoção teste',
        ]);

        $response->assertRedirect(route('personnel-movements.index'));
        $this->assertDatabaseHas('personnel_movements', [
            'type' => 'promotion',
            'employee_id' => $this->employee->id,
            'new_position_id' => 2,
        ]);
    }

    public function test_can_create_transfer_movement(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('personnel-movements.store'), [
            'type' => 'transfer',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'effective_date' => now()->addDays(5)->format('Y-m-d'),
            'origin_store_id' => $this->store->code,
            'destination_store_id' => $this->store2->code,
            'observation' => 'Transferência teste',
        ]);

        $response->assertRedirect(route('personnel-movements.index'));
        $this->assertDatabaseHas('personnel_movements', [
            'type' => 'transfer',
            'origin_store_id' => $this->store->code,
            'destination_store_id' => $this->store2->code,
        ]);
    }

    public function test_transfer_requires_different_stores(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('personnel-movements.store'), [
            'type' => 'transfer',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'effective_date' => now()->addDays(5)->format('Y-m-d'),
            'origin_store_id' => $this->store->code,
            'destination_store_id' => $this->store->code,
        ]);

        $response->assertSessionHasErrors('destination_store_id');
    }

    public function test_can_create_reactivation_movement(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('personnel-movements.store'), [
            'type' => 'reactivation',
            'employee_id' => $this->inactiveEmployee->id,
            'store_id' => $this->store->code,
            'reactivation_date' => now()->format('Y-m-d'),
            'observation' => 'Reativação teste',
        ]);

        $response->assertRedirect(route('personnel-movements.index'));
        $this->assertDatabaseHas('personnel_movements', [
            'type' => 'reactivation',
            'employee_id' => $this->inactiveEmployee->id,
        ]);
    }

    public function test_can_transition_pending_to_in_progress(): void
    {
        $movement = PersonnelMovement::create([
            'type' => 'promotion',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'status' => 'pending',
            'effective_date' => now()->addDays(5),
            'new_position_id' => 2,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('personnel-movements.transition', $movement), [
                'new_status' => 'in_progress',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('personnel_movements', [
            'id' => $movement->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_completing_promotion_updates_employee_position(): void
    {
        // Employee starts at position 1 (Vendedor), promote to 2 (Gerente)
        $movement = PersonnelMovement::create([
            'type' => 'promotion',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'status' => 'in_progress',
            'effective_date' => now(),
            'new_position_id' => 2,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('personnel-movements.transition', $movement), [
                'new_status' => 'completed',
            ]);

        $response->assertOk();
        $this->employee->refresh();
        $this->assertEquals(2, $this->employee->position_id);
    }

    public function test_completing_transfer_updates_employee_store(): void
    {
        $movement = PersonnelMovement::create([
            'type' => 'transfer',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'status' => 'in_progress',
            'effective_date' => now(),
            'origin_store_id' => $this->store->code,
            'destination_store_id' => $this->store2->code,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('personnel-movements.transition', $movement), [
                'new_status' => 'completed',
            ]);

        $response->assertOk();
        $this->employee->refresh();
        $this->assertEquals($this->store2->code, $this->employee->store_id);
    }

    public function test_completing_reactivation_clears_dismissal_date(): void
    {
        $movement = PersonnelMovement::create([
            'type' => 'reactivation',
            'employee_id' => $this->inactiveEmployee->id,
            'store_id' => $this->store->code,
            'status' => 'in_progress',
            'reactivation_date' => now(),
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('personnel-movements.transition', $movement), [
                'new_status' => 'completed',
            ]);

        $response->assertOk();
        $this->inactiveEmployee->refresh();
        $this->assertNull($this->inactiveEmployee->dismissal_date);
    }

    public function test_invalid_transition_returns_error(): void
    {
        $movement = PersonnelMovement::create([
            'type' => 'promotion',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'status' => 'completed',
            'effective_date' => now(),
            'new_position_id' => 2,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('personnel-movements.transition', $movement), [
                'new_status' => 'in_progress',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_soft_delete_pending_movement(): void
    {
        $movement = PersonnelMovement::create([
            'type' => 'promotion',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'status' => 'pending',
            'effective_date' => now(),
            'new_position_id' => 2,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('personnel-movements.destroy', $movement), [
                'deleted_reason' => 'Motivo teste',
            ]);

        $response->assertRedirect(route('personnel-movements.index'));
        $movement->refresh();
        $this->assertNotNull($movement->deleted_at);
    }

    public function test_can_view_movement_detail(): void
    {
        $movement = PersonnelMovement::create([
            'type' => 'promotion',
            'employee_id' => $this->employee->id,
            'store_id' => $this->store->code,
            'status' => 'pending',
            'effective_date' => now(),
            'new_position_id' => 2,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('personnel-movements.show', $movement));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $movement->id]);
    }

    public function test_integration_data_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('personnel-movements.integration-data', $this->employee));

        $response->assertOk();
        $response->assertJsonStructure(['fouls', 'days_off', 'overtime_hours']);
    }
}
