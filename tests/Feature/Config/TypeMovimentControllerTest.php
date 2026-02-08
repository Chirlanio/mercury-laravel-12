<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TypeMovimentControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_type_moviments_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/type-moviments');

        $response->assertOk();
    }

    public function test_type_moviment_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/type-moviments', [
            'name' => 'Recontratacao',
            'description' => 'Recontratacao de funcionario',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('type_moviments', ['name' => 'Recontratacao']);
    }

    public function test_type_moviment_with_contracts_cannot_be_deleted(): void
    {
        $this->createTestStore('Z999');
        $employeeId = $this->createTestEmployee();

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

        $response = $this->actingAs($this->adminUser)->delete('/config/type-moviments/1');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('type_moviments', ['id' => 1]);
    }
}
