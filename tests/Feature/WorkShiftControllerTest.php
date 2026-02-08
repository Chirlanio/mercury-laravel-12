<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class WorkShiftControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->createTestStore('Z999');
    }

    private function createEmployee(): int
    {
        return $this->createTestEmployee();
    }

    public function test_work_shifts_index_requires_authentication(): void
    {
        $response = $this->get('/work-shifts');

        $response->assertRedirect('/login');
    }

    public function test_work_shifts_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/work-shifts');

        $response->assertOk();
    }

    public function test_work_shifts_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/work-shifts');

        $response->assertStatus(403);
    }

    public function test_work_shift_can_be_created(): void
    {
        $employeeId = $this->createEmployee();

        $response = $this->actingAs($this->adminUser)->post('/work-shifts', [
            'employee_id' => $employeeId,
            'date' => now()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'type' => 'integral',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('work_shifts', [
            'employee_id' => $employeeId,
            'type' => 'integral',
        ]);
    }

    public function test_work_shift_requires_employee(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/work-shifts', [
            'date' => now()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'type' => 'integral',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_work_shift_requires_valid_type(): void
    {
        $employeeId = $this->createEmployee();

        $response = $this->actingAs($this->adminUser)->post('/work-shifts', [
            'employee_id' => $employeeId,
            'date' => now()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_work_shift_end_time_must_be_after_start_time(): void
    {
        $employeeId = $this->createEmployee();

        $response = $this->actingAs($this->adminUser)->post('/work-shifts', [
            'employee_id' => $employeeId,
            'date' => now()->format('Y-m-d'),
            'start_time' => '17:00',
            'end_time' => '08:00',
            'type' => 'integral',
        ]);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_work_shift_can_be_updated(): void
    {
        $employeeId = $this->createEmployee();

        $shiftId = DB::table('work_shifts')->insertGetId([
            'employee_id' => $employeeId,
            'date' => now()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'type' => 'integral',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->put("/work-shifts/{$shiftId}", [
            'employee_id' => $employeeId,
            'date' => now()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'type' => 'abertura',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('work_shifts', [
            'id' => $shiftId,
            'type' => 'abertura',
        ]);
    }

    public function test_work_shift_can_be_deleted(): void
    {
        $employeeId = $this->createEmployee();

        $shiftId = DB::table('work_shifts')->insertGetId([
            'employee_id' => $employeeId,
            'date' => now()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'type' => 'integral',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/work-shifts/{$shiftId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('work_shifts', ['id' => $shiftId]);
    }

    public function test_work_shift_show_returns_json(): void
    {
        $employeeId = $this->createEmployee();

        $shiftId = DB::table('work_shifts')->insertGetId([
            'employee_id' => $employeeId,
            'date' => now()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'type' => 'integral',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->getJson("/work-shifts/{$shiftId}");

        $response->assertOk();
    }
}
