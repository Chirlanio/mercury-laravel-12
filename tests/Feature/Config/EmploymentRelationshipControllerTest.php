<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EmploymentRelationshipControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_employment_relationships_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/employment-relationships');

        $response->assertOk();
    }

    public function test_employment_relationship_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/employment-relationships', [
            'name' => 'Estagio',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employment_relationships', ['name' => 'Estagio']);
    }

    public function test_employment_relationship_name_must_be_unique(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/employment-relationships', [
            'name' => 'CLT',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
