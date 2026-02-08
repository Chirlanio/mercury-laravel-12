<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class UserManagementControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->createTestStore('Z999');

        $this->superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN->value,
            'access_level_id' => 1,
        ]);
    }

    public function test_users_index_requires_authentication(): void
    {
        $response = $this->get('/users');

        $response->assertRedirect('/login');
    }

    public function test_users_index_is_displayed_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/users');

        $response->assertOk();
    }

    public function test_users_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/users');

        $response->assertStatus(403);
    }

    public function test_users_index_displayed_for_support(): void
    {
        $response = $this->actingAs($this->supportUser)->get('/users');

        $response->assertOk();
    }

    public function test_users_create_page_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/users/create');

        $response->assertOk();
    }

    public function test_user_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'store_id' => 'Z999',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_user_requires_name(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/users', [
            'name' => '',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'store_id' => 'Z999',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_user_email_must_be_unique(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/users', [
            'name' => 'Duplicate User',
            'email' => $this->adminUser->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'store_id' => 'Z999',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_be_viewed(): void
    {
        $response = $this->actingAs($this->adminUser)->get("/users/{$this->regularUser->id}");

        $response->assertOk();
    }

    public function test_user_can_be_updated(): void
    {
        $user = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
        ]);

        $response = $this->actingAs($this->adminUser)->put("/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'user',
            'store_id' => 'Z999',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_be_deleted(): void
    {
        $user = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/users/{$user->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->adminUser)->delete("/users/{$this->adminUser->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->adminUser->id]);
    }

    public function test_user_role_can_be_updated_by_super_admin(): void
    {
        $user = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
        ]);

        $response = $this->actingAs($this->superAdmin)->patch("/users/{$user->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_user_role_update_blocked_for_admin(): void
    {
        $user = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
        ]);

        $response = $this->actingAs($this->adminUser)->patch("/users/{$user->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_creation_blocked_without_permission(): void
    {
        $response = $this->actingAs($this->supportUser)->post('/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'store_id' => 'Z999',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_deletion_blocked_without_permission(): void
    {
        $user = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
        ]);

        $response = $this->actingAs($this->supportUser)->delete("/users/{$user->id}");

        $response->assertStatus(403);
    }
}
