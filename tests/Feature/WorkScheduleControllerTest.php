<?php

namespace Tests\Feature;

use App\Models\EmployeeScheduleDayOverride;
use App\Models\EmployeeWorkSchedule;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class WorkScheduleControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->createTestStore('Z999');
    }

    // ==================== INDEX ====================

    public function test_index_requires_auth(): void
    {
        $response = $this->get('/work-schedules');
        $response->assertRedirect('/login');
    }

    public function test_index_displayed_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/work-schedules');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('WorkSchedules/Index'));
    }

    public function test_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/work-schedules');
        $response->assertStatus(403);
    }

    // ==================== STORE ====================

    public function test_schedule_can_be_created(): void
    {
        $payload = $this->buildSchedulePayload('ESCALA TESTE');

        $response = $this->actingAs($this->adminUser)->post('/work-schedules', $payload);

        $response->assertRedirect(route('work-schedules.index'));
        $this->assertDatabaseHas('work_schedules', ['name' => 'ESCALA TESTE']);
        $this->assertDatabaseCount('work_schedule_days', 7);
    }

    public function test_schedule_requires_name(): void
    {
        $payload = $this->buildSchedulePayload('');
        $payload['name'] = '';

        $response = $this->actingAs($this->adminUser)->post('/work-schedules', $payload);
        $response->assertSessionHasErrors('name');
    }

    public function test_schedule_name_must_be_unique(): void
    {
        $this->createTestWorkSchedule(['name' => 'DUPLICADA']);

        $payload = $this->buildSchedulePayload('DUPLICADA');

        $response = $this->actingAs($this->adminUser)->post('/work-schedules', $payload);
        $response->assertSessionHasErrors('name');
    }

    public function test_schedule_requires_7_days(): void
    {
        $payload = $this->buildSchedulePayload('TEST');
        $payload['days'] = array_slice($payload['days'], 0, 3);

        $response = $this->actingAs($this->adminUser)->post('/work-schedules', $payload);
        $response->assertSessionHasErrors('days');
    }

    // ==================== SHOW / EDIT ====================

    public function test_show_returns_json_with_days(): void
    {
        $schedule = $this->createTestWorkSchedule();

        $response = $this->actingAs($this->adminUser)->getJson("/work-schedules/{$schedule->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'schedule' => [
                'id', 'name', 'weekly_hours', 'is_active', 'days', 'employees',
            ],
        ]);
    }

    public function test_edit_returns_json(): void
    {
        $schedule = $this->createTestWorkSchedule();

        $response = $this->actingAs($this->adminUser)->getJson("/work-schedules/{$schedule->id}/edit");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'schedule' => ['id', 'name', 'days'],
        ]);
    }

    // ==================== UPDATE ====================

    public function test_schedule_can_be_updated(): void
    {
        $schedule = $this->createTestWorkSchedule(['name' => 'ORIGINAL']);

        $payload = $this->buildSchedulePayload('ATUALIZADA');

        $response = $this->actingAs($this->adminUser)->put("/work-schedules/{$schedule->id}/update", $payload);

        $response->assertRedirect(route('work-schedules.index'));
        $this->assertDatabaseHas('work_schedules', ['id' => $schedule->id, 'name' => 'ATUALIZADA']);
    }

    // ==================== DESTROY ====================

    public function test_schedule_can_be_deleted(): void
    {
        $schedule = $this->createTestWorkSchedule();

        $response = $this->actingAs($this->adminUser)->delete("/work-schedules/{$schedule->id}");

        $response->assertRedirect(route('work-schedules.index'));
        $this->assertDatabaseMissing('work_schedules', ['id' => $schedule->id]);
    }

    public function test_cannot_delete_schedule_with_active_assignments(): void
    {
        $schedule = $this->createTestWorkSchedule();
        $employeeId = $this->createTestEmployee();

        EmployeeWorkSchedule::create([
            'employee_id' => $employeeId,
            'work_schedule_id' => $schedule->id,
            'effective_date' => now()->subMonth(),
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/work-schedules/{$schedule->id}");

        $response->assertSessionHasErrors('general');
        $this->assertDatabaseHas('work_schedules', ['id' => $schedule->id]);
    }

    // ==================== ASSIGN / UNASSIGN ====================

    public function test_employee_can_be_assigned(): void
    {
        $schedule = $this->createTestWorkSchedule();
        $employeeId = $this->createTestEmployee();

        $response = $this->actingAs($this->adminUser)->postJson("/work-schedules/{$schedule->id}/employees", [
            'employee_id' => $employeeId,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employee_work_schedules', [
            'employee_id' => $employeeId,
            'work_schedule_id' => $schedule->id,
        ]);
    }

    public function test_assignment_requires_effective_date(): void
    {
        $schedule = $this->createTestWorkSchedule();
        $employeeId = $this->createTestEmployee();

        $response = $this->actingAs($this->adminUser)->postJson("/work-schedules/{$schedule->id}/employees", [
            'employee_id' => $employeeId,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('effective_date');
    }

    public function test_employee_can_be_unassigned(): void
    {
        $schedule = $this->createTestWorkSchedule();
        $employeeId = $this->createTestEmployee();

        $assignment = EmployeeWorkSchedule::create([
            'employee_id' => $employeeId,
            'work_schedule_id' => $schedule->id,
            'effective_date' => now()->subMonth(),
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson("/work-schedules/{$schedule->id}/employees/{$assignment->id}");

        $response->assertStatus(200);
        $this->assertNotNull(EmployeeWorkSchedule::find($assignment->id)->end_date);
    }

    // ==================== OVERRIDES ====================

    public function test_day_override_can_be_created(): void
    {
        $schedule = $this->createTestWorkSchedule();
        $employeeId = $this->createTestEmployee();

        $assignment = EmployeeWorkSchedule::create([
            'employee_id' => $employeeId,
            'work_schedule_id' => $schedule->id,
            'effective_date' => now()->subMonth(),
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->postJson("/employee-schedules/{$assignment->id}/overrides", [
            'day_of_week' => 0,
            'is_work_day' => true,
            'entry_time' => '09:00',
            'exit_time' => '18:00',
            'reason' => 'Compensação',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employee_schedule_day_overrides', [
            'employee_work_schedule_id' => $assignment->id,
            'day_of_week' => 0,
            'is_work_day' => true,
        ]);
    }

    public function test_day_override_can_be_destroyed(): void
    {
        $schedule = $this->createTestWorkSchedule();
        $employeeId = $this->createTestEmployee();

        $assignment = EmployeeWorkSchedule::create([
            'employee_id' => $employeeId,
            'work_schedule_id' => $schedule->id,
            'effective_date' => now()->subMonth(),
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $override = EmployeeScheduleDayOverride::create([
            'employee_work_schedule_id' => $assignment->id,
            'day_of_week' => 0,
            'is_work_day' => true,
            'entry_time' => '09:00',
            'exit_time' => '18:00',
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson("/employee-schedules/{$assignment->id}/overrides/{$override->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('employee_schedule_day_overrides', ['id' => $override->id]);
    }

    // ==================== DUPLICATE ====================

    public function test_duplicate_creates_copy(): void
    {
        $schedule = $this->createTestWorkSchedule(['name' => 'ORIGINAL PARA COPIAR']);

        $response = $this->actingAs($this->adminUser)->post("/work-schedules/{$schedule->id}/duplicate");

        $response->assertRedirect(route('work-schedules.index'));
        $this->assertDatabaseHas('work_schedules', ['name' => 'ORIGINAL PARA COPIAR (CÓPIA)']);
    }

    // ==================== SUPPORT USER ====================

    public function test_support_can_view_but_not_create(): void
    {
        $viewResponse = $this->actingAs($this->supportUser)->get('/work-schedules');
        $viewResponse->assertStatus(200);

        $payload = $this->buildSchedulePayload('SUPPORT TEST');
        $createResponse = $this->actingAs($this->supportUser)->post('/work-schedules', $payload);
        $createResponse->assertStatus(403);
    }

    // ==================== HELPERS ====================

    private function buildSchedulePayload(string $name): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $isWorkDay = $i >= 1 && $i <= 5;
            $days[] = [
                'day_of_week' => $i,
                'is_work_day' => $isWorkDay,
                'entry_time' => $isWorkDay ? '08:00' : null,
                'exit_time' => $isWorkDay ? '17:48' : null,
                'break_start' => $isWorkDay ? '12:00' : null,
                'break_end' => $isWorkDay ? '13:00' : null,
                'break_duration_minutes' => $isWorkDay ? 60 : null,
            ];
        }

        return [
            'name' => $name,
            'description' => 'Test description',
            'is_active' => true,
            'is_default' => false,
            'days' => $days,
        ];
    }
}
