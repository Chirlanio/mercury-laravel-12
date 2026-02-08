<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SectorControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_sectors_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/sectors');

        $response->assertOk();
    }

    public function test_sector_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/sectors', [
            'sector_name' => 'Financeiro',
            'area_manager_id' => 1,
            'sector_manager_id' => 2,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sectors', ['sector_name' => 'Financeiro']);
    }

    public function test_sector_name_must_be_unique(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/sectors', [
            'sector_name' => 'Comercial',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('sector_name');
    }
}
