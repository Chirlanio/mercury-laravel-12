<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ManagerControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_managers_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/managers');

        $response->assertOk();
    }

    public function test_manager_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/managers', [
            'name' => 'Joao Silva',
            'email' => 'joao@example.com',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('managers', ['name' => 'Joao Silva', 'email' => 'joao@example.com']);
    }

    public function test_manager_linked_to_sector_cannot_be_deleted(): void
    {
        $managerId = DB::table('managers')->insertGetId([
            'name' => 'Manager Test',
            'email' => 'manager@test.com',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sectors')->where('id', 1)->update(['area_manager_id' => $managerId]);

        $response = $this->actingAs($this->adminUser)->delete("/config/managers/{$managerId}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('managers', ['id' => $managerId]);
    }
}
