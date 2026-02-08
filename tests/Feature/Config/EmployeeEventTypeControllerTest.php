<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EmployeeEventTypeControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_employee_event_types_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/employee-event-types');

        $response->assertOk();
    }

    public function test_employee_event_type_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/employee-event-types', [
            'name' => 'Licenca Maternidade',
            'description' => 'Licenca maternidade de 120 dias',
            'requires_document' => true,
            'requires_date_range' => true,
            'requires_single_date' => false,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_event_types', ['name' => 'Licenca Maternidade']);
    }

    public function test_employee_event_type_with_events_cannot_be_deleted(): void
    {
        $this->createTestStore('Z999');
        $employeeId = $this->createTestEmployee();

        DB::table('employee_events')->insert([
            'employee_id' => $employeeId,
            'event_type_id' => 1,
            'start_date' => now(),
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->delete('/config/employee-event-types/1');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('employee_event_types', ['id' => 1]);
    }
}
