<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EmployeeStatusControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_employee_statuses_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/employee-statuses');

        $response->assertOk();
    }

    public function test_employee_status_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/employee-statuses', [
            'description_name' => 'Afastado',
            'color_theme_id' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_statuses', ['description_name' => 'Afastado']);
    }

    public function test_employee_status_can_be_updated(): void
    {
        $response = $this->actingAs($this->adminUser)->put('/config/employee-statuses/1', [
            'description_name' => 'Pendente Atualizado',
            'color_theme_id' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_statuses', ['id' => 1, 'description_name' => 'Pendente Atualizado']);
    }
}
