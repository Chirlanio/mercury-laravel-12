<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PageControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN->value,
            'access_level_id' => 1,
        ]);
    }

    public function test_pages_index_requires_authentication(): void
    {
        $response = $this->get('/pages');

        $response->assertRedirect('/login');
    }

    public function test_pages_index_is_displayed_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/pages');

        $response->assertOk();
    }

    public function test_pages_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/pages');

        $response->assertStatus(403);
    }

    public function test_page_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/pages', [
            'page_name' => 'Test Page',
            'controller' => 'TestController',
            'method' => 'index',
            'menu_controller' => 'TestController',
            'menu_method' => 'index',
            'route' => '/test-page',
            'notes' => 'Test notes',
            'page_group_id' => 1,
            'is_active' => true,
            'is_public' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pages', ['page_name' => 'Test Page']);
    }

    public function test_page_requires_page_name(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/pages', [
            'page_name' => '',
            'controller' => 'TestController',
            'method' => 'index',
            'route' => '/test',
            'page_group_id' => 1,
        ]);

        $response->assertSessionHasErrors('page_name');
    }

    public function test_page_requires_controller(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/pages', [
            'page_name' => 'Test',
            'controller' => '',
            'method' => 'index',
            'route' => '/test',
            'page_group_id' => 1,
        ]);

        $response->assertSessionHasErrors('controller');
    }

    public function test_page_can_be_updated(): void
    {
        $pageId = $this->createTestPage(['page_name' => 'Original Page', 'controller' => 'OriginalController', 'route' => '/original']);

        $response = $this->actingAs($this->adminUser)->patch("/pages/{$pageId}", [
            'page_name' => 'Updated Page',
            'controller' => 'UpdatedController',
            'method' => 'index',
            'menu_controller' => 'UpdatedController',
            'menu_method' => 'index',
            'route' => '/updated',
            'notes' => 'Updated notes',
            'page_group_id' => 1,
            'is_active' => true,
            'is_public' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pages', ['id' => $pageId, 'page_name' => 'Updated Page']);
    }

    public function test_page_can_be_activated(): void
    {
        $pageId = $this->createTestPage(['page_name' => 'Inactive Page', 'controller' => 'InactiveController', 'route' => '/inactive', 'is_active' => false]);

        $response = $this->actingAs($this->superAdmin)->post("/pages/{$pageId}/activate");

        $response->assertRedirect();
        $this->assertDatabaseHas('pages', ['id' => $pageId, 'is_active' => true]);
    }

    public function test_page_can_be_deactivated(): void
    {
        $pageId = $this->createTestPage(['page_name' => 'Active Page', 'controller' => 'ActiveController', 'route' => '/active']);

        $response = $this->actingAs($this->superAdmin)->post("/pages/{$pageId}/deactivate");

        $response->assertRedirect();
        $this->assertDatabaseHas('pages', ['id' => $pageId, 'is_active' => false]);
    }

    public function test_page_can_be_made_public(): void
    {
        $pageId = $this->createTestPage(['page_name' => 'Private Page', 'controller' => 'PrivateController', 'route' => '/private']);

        $response = $this->actingAs($this->superAdmin)->post("/pages/{$pageId}/make-public");

        $response->assertRedirect();
        $this->assertDatabaseHas('pages', ['id' => $pageId, 'is_public' => true]);
    }

    public function test_page_can_be_made_private(): void
    {
        $pageId = $this->createTestPage(['page_name' => 'Public Page', 'controller' => 'PublicController', 'route' => '/public', 'is_public' => true]);

        $response = $this->actingAs($this->superAdmin)->post("/pages/{$pageId}/make-private");

        $response->assertRedirect();
        $this->assertDatabaseHas('pages', ['id' => $pageId, 'is_public' => false]);
    }

    public function test_page_can_be_deleted(): void
    {
        $pageId = $this->createTestPage(['page_name' => 'Delete Me', 'controller' => 'DeleteController', 'route' => '/delete-me']);

        $response = $this->actingAs($this->superAdmin)->delete("/pages/{$pageId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('pages', ['id' => $pageId]);
    }

    public function test_page_show_returns_data(): void
    {
        $pageId = $this->createTestPage(['page_name' => 'Show Page', 'controller' => 'ShowController', 'route' => '/show']);

        $response = $this->actingAs($this->adminUser)->get("/pages/{$pageId}");

        $response->assertOk();
    }
}
