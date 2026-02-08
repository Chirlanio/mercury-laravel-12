<?php

namespace Tests\Feature\Config;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PageStatusControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_page_statuses_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/page-statuses');

        $response->assertOk();
    }

    public function test_page_status_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/page-statuses', [
            'name' => 'Arquivado',
            'color' => 'secondary',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('page_statuses', ['name' => 'Arquivado']);
    }

    public function test_page_status_can_be_updated(): void
    {
        $response = $this->actingAs($this->adminUser)->put('/config/page-statuses/1', [
            'name' => 'Publicado Atualizado',
            'color' => 'primary',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('page_statuses', ['id' => 1, 'name' => 'Publicado Atualizado']);
    }
}
