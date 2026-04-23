<?php

namespace Tests\Feature\Config;

use App\Models\SocialMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SocialMediaControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/config/social-media')->assertRedirect('/login');
    }

    public function test_index_blocked_without_manage_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/config/social-media')
            ->assertStatus(403);
    }

    public function test_admin_sees_index_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/config/social-media')
            ->assertOk();
    }

    public function test_admin_can_create_social_media(): void
    {
        $this->actingAs($this->adminUser)
            ->post('/config/social-media', [
                'name' => 'Pinterest',
                'icon' => 'fa-brands fa-pinterest',
                'link_type' => 'url',
                'link_placeholder' => 'https://pinterest.com/usuario',
                'sort_order' => 60,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('social_media', ['name' => 'Pinterest', 'link_type' => 'url']);
    }

    public function test_name_must_be_unique(): void
    {
        SocialMedia::create(['name' => 'LinkedIn', 'is_active' => true, 'sort_order' => 0]);

        $this->actingAs($this->adminUser)
            ->post('/config/social-media', ['name' => 'LinkedIn', 'is_active' => true])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_social_media(): void
    {
        $row = SocialMedia::create(['name' => 'Threads', 'link_type' => 'username', 'is_active' => true, 'sort_order' => 70]);

        $this->actingAs($this->adminUser)
            ->put('/config/social-media/'.$row->id, [
                'name' => 'Threads',
                'icon' => 'fa-brands fa-threads',
                'link_type' => 'username',
                'link_placeholder' => '@usuario',
                'sort_order' => 70,
                'is_active' => false,
            ])
            ->assertRedirect();

        $row->refresh();
        $this->assertFalse($row->is_active);
        $this->assertSame('fa-brands fa-threads', $row->icon);
    }

    public function test_admin_can_delete_social_media(): void
    {
        $row = SocialMedia::create(['name' => 'Snapchat', 'is_active' => true, 'sort_order' => 80]);

        $this->actingAs($this->adminUser)
            ->delete('/config/social-media/'.$row->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('social_media', ['id' => $row->id]);
    }
}
