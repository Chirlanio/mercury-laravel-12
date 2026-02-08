<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class MenuControllerTest extends TestCase
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

    public function test_menus_index_requires_authentication(): void
    {
        $response = $this->get('/menus');

        $response->assertRedirect('/login');
    }

    public function test_menus_index_is_displayed_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/menus');

        $response->assertOk();
    }

    public function test_menus_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/menus');

        $response->assertStatus(403);
    }

    public function test_menu_can_be_created(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/menus', [
            'name' => 'Dashboard',
            'icon' => 'dashboard',
            'order' => 1,
            'type' => 'main',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('menus', ['name' => 'Dashboard']);
    }

    public function test_menu_requires_name(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/menus', [
            'name' => '',
            'order' => 1,
            'type' => 'main',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_menu_requires_valid_type(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/menus', [
            'name' => 'Test',
            'order' => 1,
            'type' => 'invalid',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_menu_can_be_updated(): void
    {
        $menuId = DB::table('menus')->insertGetId([
            'name' => 'Original Menu',
            'icon' => 'home',
            'order' => 1,
            'type' => 'main',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->put("/menus/{$menuId}", [
            'name' => 'Updated Menu',
            'order' => 2,
            'type' => 'main',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('menus', ['id' => $menuId, 'name' => 'Updated Menu']);
    }

    public function test_menu_cannot_be_its_own_parent(): void
    {
        $menuId = DB::table('menus')->insertGetId([
            'name' => 'Self Parent',
            'icon' => 'home',
            'order' => 1,
            'type' => 'main',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->put("/menus/{$menuId}", [
            'name' => 'Self Parent',
            'order' => 1,
            'type' => 'main',
            'parent_id' => $menuId,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_menu_can_be_activated(): void
    {
        $menuId = DB::table('menus')->insertGetId([
            'name' => 'Inactive Menu',
            'icon' => 'home',
            'order' => 1,
            'type' => 'main',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->post("/menus/{$menuId}/activate");

        $response->assertRedirect();
        $this->assertDatabaseHas('menus', ['id' => $menuId, 'is_active' => true]);
    }

    public function test_menu_can_be_deactivated(): void
    {
        $menuId = DB::table('menus')->insertGetId([
            'name' => 'Active Menu',
            'icon' => 'home',
            'order' => 1,
            'type' => 'main',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->post("/menus/{$menuId}/deactivate");

        $response->assertRedirect();
        $this->assertDatabaseHas('menus', ['id' => $menuId, 'is_active' => false]);
    }

    public function test_menu_with_children_cannot_be_deleted(): void
    {
        $parentId = DB::table('menus')->insertGetId([
            'name' => 'Parent Menu',
            'icon' => 'folder',
            'order' => 1,
            'type' => 'main',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            'name' => 'Child Menu',
            'icon' => 'file',
            'order' => 2,
            'type' => 'main',
            'parent_id' => $parentId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->delete("/menus/{$parentId}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('menus', ['id' => $parentId]);
    }

    public function test_menu_without_children_can_be_deleted(): void
    {
        $menuId = DB::table('menus')->insertGetId([
            'name' => 'Leaf Menu',
            'icon' => 'home',
            'order' => 1,
            'type' => 'main',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->delete("/menus/{$menuId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('menus', ['id' => $menuId]);
    }

    public function test_sidebar_api_returns_json(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/menus/sidebar');

        $response->assertOk();
        $response->assertJsonStructure(['main', 'hr', 'utility', 'system']);
    }
}
