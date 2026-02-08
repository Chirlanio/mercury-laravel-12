<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PositionControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_positions_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/positions');

        $response->assertOk();
    }

    public function test_position_can_be_created_with_level_and_status(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/positions', [
            'name' => 'Analista',
            'level' => 'Pleno',
            'level_category_id' => 1,
            'status_id' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('positions', ['name' => 'Analista', 'level' => 'Pleno']);
    }

    public function test_position_with_employees_cannot_be_deleted(): void
    {
        $this->createTestStore('Z999');
        $this->createTestEmployee(['position_id' => 1]);

        $response = $this->actingAs($this->adminUser)->delete('/config/positions/1');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('positions', ['id' => 1]);
    }

    public function test_unused_position_can_be_deleted(): void
    {
        $positionId = DB::table('positions')->insertGetId([
            'name' => 'Temporario',
            'level' => 'Junior',
            'level_category_id' => 1,
            'status_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/config/positions/{$positionId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('positions', ['id' => $positionId]);
    }
}
