<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class GenderControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_genders_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/genders');

        $response->assertOk();
    }

    public function test_gender_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/genders', [
            'description_name' => 'Nao-binario',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('genders', ['description_name' => 'Nao-binario']);
    }

    public function test_gender_with_employees_cannot_be_deleted(): void
    {
        $this->createTestStore('Z999');
        $this->createTestEmployee(['gender_id' => 1]);

        $response = $this->actingAs($this->adminUser)->delete('/config/genders/1');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('genders', ['id' => 1]);
    }
}
