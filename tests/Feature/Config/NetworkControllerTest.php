<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class NetworkControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_networks_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/networks');

        $response->assertOk();
    }

    public function test_network_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/networks', [
            'nome' => 'Nova Rede',
            'type' => 'comercial',
            'active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('networks', ['nome' => 'Nova Rede']);
    }

    public function test_network_name_must_be_unique(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/networks', [
            'nome' => 'Arezzo',
            'type' => 'comercial',
            'active' => true,
        ]);

        $response->assertSessionHasErrors('nome');
    }
}
