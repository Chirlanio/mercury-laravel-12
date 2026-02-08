<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StatusControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_statuses_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/statuses');

        $response->assertOk();
    }

    public function test_status_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/statuses', [
            'name' => 'Suspenso',
            'color_theme_id' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('statuses', ['name' => 'Suspenso']);
    }

    public function test_status_can_be_updated(): void
    {
        $statusId = DB::table('statuses')->insertGetId([
            'name' => 'Temporario',
            'color_theme_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->put("/config/statuses/{$statusId}", [
            'name' => 'Temporario Atualizado',
            'color_theme_id' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('statuses', ['id' => $statusId, 'name' => 'Temporario Atualizado']);
    }
}
