<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ColorThemeControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_color_themes_index_requires_authentication(): void
    {
        $response = $this->get('/color-themes');

        $response->assertRedirect('/login');
    }

    public function test_color_themes_index_is_displayed(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/color-themes');

        $response->assertOk();
    }

    public function test_color_theme_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/color-themes', [
            'name' => 'Purple',
            'color_class' => 'bg-purple-500',
            'hex_color' => '#a855f7',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('color_themes', ['name' => 'Purple']);
    }

    public function test_color_theme_name_must_be_unique(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/color-themes', [
            'name' => 'Green',
            'hex_color' => '#22c55e',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_color_theme_hex_color_is_required(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/color-themes', [
            'name' => 'Yellow',
        ]);

        $response->assertSessionHasErrors('hex_color');
    }

    public function test_color_theme_can_be_updated(): void
    {
        $themeId = DB::table('color_themes')->where('name', 'Green')->value('id');

        $response = $this->actingAs($this->adminUser)->put("/color-themes/{$themeId}", [
            'name' => 'Dark Green',
            'hex_color' => '#166534',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('color_themes', ['id' => $themeId, 'name' => 'Dark Green']);
    }

    public function test_color_theme_used_by_access_levels_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->adminUser)->delete('/color-themes/1');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('color_themes', ['id' => 1]);
    }

    public function test_unused_color_theme_can_be_deleted(): void
    {
        $themeId = DB::table('color_themes')->insertGetId([
            'name' => 'Temp Theme',
            'color_class' => 'bg-gray-500',
            'hex_color' => '#6b7280',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/color-themes/{$themeId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('color_themes', ['id' => $themeId]);
    }
}
