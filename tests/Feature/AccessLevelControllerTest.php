<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccessLevelControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_access_levels_index_requires_authentication(): void
    {
        $response = $this->get('/access-levels');

        $response->assertRedirect('/login');
    }

    public function test_access_levels_index_is_displayed_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/access-levels');

        $response->assertOk();
    }

    public function test_access_levels_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/access-levels');

        $response->assertStatus(403);
    }

    public function test_access_level_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/access-levels', [
            'name' => 'Diretor',
            'color_theme_id' => 1,
            'order' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('access_levels', ['name' => 'Diretor']);
    }

    public function test_access_level_name_must_be_unique(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/access-levels', [
            'name' => 'Admin',
            'color_theme_id' => 1,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_access_level_can_be_updated(): void
    {
        $levelId = DB::table('access_levels')->insertGetId([
            'name' => 'Original Level',
            'order' => 5,
            'color_theme_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->put("/access-levels/{$levelId}", [
            'name' => 'Updated Level',
            'color_theme_id' => 2,
            'order' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('access_levels', ['id' => $levelId, 'name' => 'Updated Level']);
    }

    public function test_access_level_can_be_deleted(): void
    {
        $levelId = DB::table('access_levels')->insertGetId([
            'name' => 'Deletable Level',
            'order' => 10,
            'color_theme_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/access-levels/{$levelId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('access_levels', ['id' => $levelId]);
    }

    public function test_access_level_show_returns_data(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/access-levels/1');

        $response->assertOk();
    }

    public function test_access_level_permissions_can_be_retrieved(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/access-levels/1/permissions');

        $response->assertOk();
    }

    public function test_access_level_permissions_can_be_updated(): void
    {
        $pageId = $this->createTestPage();

        $response = $this->actingAs($this->adminUser)->post('/access-levels/1/permissions', [
            'permissions' => [
                [
                    'page_id' => $pageId,
                    'has_permission' => true,
                    'order' => 1,
                    'dropdown' => false,
                    'lib_menu' => true,
                    'menu_id' => null,
                ],
            ],
        ]);

        $response->assertRedirect();
    }

    public function test_create_access_level_blocked_without_create_permission(): void
    {
        $response = $this->actingAs($this->supportUser)->post('/access-levels', [
            'name' => 'Should Fail',
            'color_theme_id' => 1,
        ]);

        $response->assertStatus(403);
    }
}
