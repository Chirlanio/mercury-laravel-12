<?php

namespace Tests\Feature\Config;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ConfigControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_config_index_requires_authentication(): void
    {
        $response = $this->get('/config/genders');

        $response->assertRedirect('/login');
    }

    public function test_config_index_requires_manage_settings_permission(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/config/genders');

        $response->assertStatus(403);
    }

    public function test_config_index_displays_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/genders');

        $response->assertOk();
    }

    public function test_config_index_displays_for_super_admin(): void
    {
        $superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN->value,
            'access_level_id' => 1,
        ]);

        $response = $this->actingAs($superAdmin)->get('/config/genders');

        $response->assertOk();
    }

    public function test_config_index_blocked_for_support(): void
    {
        $response = $this->actingAs($this->supportUser)->get('/config/genders');

        $response->assertStatus(403);
    }

    public function test_config_index_can_search(): void
    {
        DB::table('genders')->insert([
            'description_name' => 'Nao-binario',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->get('/config/genders?search=Nao-binario');

        $response->assertOk();
    }

    public function test_config_index_can_sort(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/config/genders?sort=description_name&direction=desc');

        $response->assertOk();
    }

    public function test_config_store_creates_item(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/genders', [
            'description_name' => 'Outro',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('genders', ['description_name' => 'Outro']);
    }

    public function test_config_store_validates_required(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/genders', [
            'description_name' => '',
        ]);

        $response->assertSessionHasErrors('description_name');
    }

    public function test_config_store_validates_unique(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/genders', [
            'description_name' => 'Masculino',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('description_name');
    }

    public function test_config_update_modifies_item(): void
    {
        $genderId = DB::table('genders')->where('description_name', 'Masculino')->value('id');

        $response = $this->actingAs($this->adminUser)->put("/config/genders/{$genderId}", [
            'description_name' => 'Masculino Atualizado',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('genders', [
            'id' => $genderId,
            'description_name' => 'Masculino Atualizado',
        ]);
    }

    public function test_config_update_validates_unique_except_self(): void
    {
        $genderId = DB::table('genders')->where('description_name', 'Masculino')->value('id');

        $response = $this->actingAs($this->adminUser)->put("/config/genders/{$genderId}", [
            'description_name' => 'Masculino',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_config_update_rejects_duplicate_name(): void
    {
        $genderId = DB::table('genders')->where('description_name', 'Masculino')->value('id');

        $response = $this->actingAs($this->adminUser)->put("/config/genders/{$genderId}", [
            'description_name' => 'Feminino',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('description_name');
    }

    public function test_config_destroy_deletes_item(): void
    {
        $newId = DB::table('genders')->insertGetId([
            'description_name' => 'Para Excluir',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/config/genders/{$newId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('genders', ['id' => $newId]);
    }

    public function test_config_destroy_blocked_when_in_use(): void
    {
        $this->createTestStore('Z999');
        $this->createTestEmployee(['gender_id' => 1]);

        $response = $this->actingAs($this->adminUser)->delete('/config/genders/1');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('genders', ['id' => 1]);
    }

    public function test_config_store_requires_permission(): void
    {
        $response = $this->actingAs($this->regularUser)->post('/config/genders', [
            'description_name' => 'Novo',
            'is_active' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_config_boolean_defaults_to_false_when_not_sent(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/config/genders', [
            'description_name' => 'Sem is_active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('genders', [
            'description_name' => 'Sem is_active',
            'is_active' => false,
        ]);
    }
}
