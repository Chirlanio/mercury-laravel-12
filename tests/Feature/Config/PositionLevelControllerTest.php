<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PositionLevelControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_position_levels_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/position-levels');

        $response->assertOk();
    }

    public function test_position_level_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/position-levels', [
            'name' => 'Nivel Novo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('position_levels', ['name' => 'Nivel Novo']);
    }

    public function test_position_level_name_must_be_unique(): void
    {
        DB::table('position_levels')->insert([
            'name' => 'Nivel Unico',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->post('/config/position-levels', [
            'name' => 'Nivel Unico',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
