<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PageGroupControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_page_groups_index_requires_authentication(): void
    {
        $response = $this->get('/page-groups');

        $response->assertRedirect('/login');
    }

    public function test_page_groups_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/page-groups');

        $response->assertOk();
    }

    public function test_page_group_can_be_created(): void
    {
        $superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN->value,
            'access_level_id' => 1,
        ]);

        $response = $this->actingAs($superAdmin)->post('/page-groups', [
            'name' => 'Novo Grupo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('page_groups', ['name' => 'Novo Grupo']);
    }

    public function test_page_group_name_must_be_unique(): void
    {
        $superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN->value,
            'access_level_id' => 1,
        ]);

        $response = $this->actingAs($superAdmin)->post('/page-groups', [
            'name' => 'Sistema',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_page_group_with_pages_cannot_be_deleted(): void
    {
        $superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN->value,
            'access_level_id' => 1,
        ]);

        $this->createTestPage(['page_group_id' => 1]);

        $response = $this->actingAs($superAdmin)->delete('/page-groups/1');

        $response->assertRedirect();
        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('page_groups', ['id' => 1]);
    }

    public function test_empty_page_group_can_be_deleted(): void
    {
        $superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN->value,
            'access_level_id' => 1,
        ]);

        $groupId = DB::table('page_groups')->insertGetId([
            'name' => 'Para Excluir',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin)->delete("/page-groups/{$groupId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('page_groups', ['id' => $groupId]);
    }
}
