<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_avatar_during_creation(): void
    {
        // Criar usuário admin para fazer a requisição
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        // Criar arquivo de imagem fake
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        // Fazer requisição de criação de usuário com avatar
        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'avatar' => $file,
        ]);

        // Verificar se foi criado com sucesso
        $response->assertRedirect('/users');

        // Verificar se usuário foi criado
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        // Verificar se avatar foi salvo
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_user_avatar_validation_rejects_invalid_files(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        // Testar arquivo muito grande (simulado)
        $largeFile = UploadedFile::fake()->create('large.jpg', 6000); // 6MB

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'avatar' => $largeFile,
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_user_can_update_avatar(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('new-avatar.png', 150, 150);

        $response = $this->actingAs($admin)->patch("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'avatar' => $file,
        ]);

        $response->assertRedirect('/users');

        $user->refresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_user_can_remove_avatar(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        // Criar usuário com avatar
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $user = User::factory()->create();

        // Simular que já tem avatar
        $avatarPath = 'avatars/test-avatar.jpg';
        Storage::disk('public')->put($avatarPath, $file->getContent());
        $user->update(['avatar' => $avatarPath]);

        // Remover avatar
        $response = $this->actingAs($admin)->delete("/users/{$user->id}/avatar");

        $response->assertRedirect();

        $user->refresh();
        $this->assertNull($user->avatar);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_user_avatar_methods(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        // Testar métodos sem avatar personalizado
        $this->assertFalse($user->hasCustomAvatar());
        $this->assertEquals('JD', $user->getInitials());

        // Testar URL padrão
        $defaultUrl = $user->getDefaultAvatarUrl();
        $this->assertStringContains('ui-avatars.com', $defaultUrl);
        $this->assertStringContains('JD', $defaultUrl);

        // Simular avatar personalizado
        $user->avatar = 'avatars/test.jpg';
        $avatarUrl = $user->avatar_url;
        $this->assertStringContains('/storage/avatars/test.jpg', $avatarUrl);
    }

    public function test_user_avatar_deletion_when_user_is_deleted(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        // Criar usuário com avatar
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $user = User::factory()->create();

        // Simular avatar
        $avatarPath = 'avatars/test-avatar.jpg';
        Storage::disk('public')->put($avatarPath, $file->getContent());
        $user->update(['avatar' => $avatarPath]);

        // Deletar usuário
        $response = $this->actingAs($admin)->delete("/users/{$user->id}");

        $response->assertRedirect('/users');

        // Verificar se avatar foi removido
        Storage::disk('public')->assertMissing($avatarPath);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
