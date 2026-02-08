<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EducationLevelControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_education_levels_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/education-levels');

        $response->assertOk();
    }

    public function test_education_level_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/education-levels', [
            'description_name' => 'Doutorado',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('education_levels', ['description_name' => 'Doutorado']);
    }

    public function test_education_level_with_employees_cannot_be_deleted(): void
    {
        $this->createTestStore('Z999');
        $this->createTestEmployee(['education_level_id' => 1]);

        $response = $this->actingAs($this->adminUser)->delete('/config/education-levels/1');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('education_levels', ['id' => 1]);
    }
}
