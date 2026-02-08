<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ActivityLogControllerTest extends TestCase
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

    private function createLogEntry(array $overrides = []): int
    {
        return DB::table('activity_logs')->insertGetId(array_merge([
            'user_id' => $this->adminUser->id,
            'action' => 'create',
            'description' => 'Test log entry',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_activity_logs_index_requires_authentication(): void
    {
        $response = $this->get('/activity-logs');

        $response->assertRedirect('/login');
    }

    public function test_activity_logs_index_is_displayed_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/activity-logs');

        $response->assertOk();
    }

    public function test_activity_logs_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/activity-logs');

        $response->assertStatus(403);
    }

    public function test_activity_logs_index_displayed_for_support(): void
    {
        $response = $this->actingAs($this->supportUser)->get('/activity-logs');

        $response->assertOk();
    }

    public function test_activity_log_show_returns_data(): void
    {
        $logId = $this->createLogEntry();

        $response = $this->actingAs($this->adminUser)->get("/activity-logs/{$logId}");

        $response->assertOk();
    }

    public function test_activity_logs_can_be_filtered_by_action(): void
    {
        $this->createLogEntry(['action' => 'login']);
        $this->createLogEntry(['action' => 'create']);

        $response = $this->actingAs($this->adminUser)->get('/activity-logs?action=login');

        $response->assertOk();
    }

    public function test_activity_logs_can_be_searched(): void
    {
        $this->createLogEntry(['description' => 'Created user john']);

        $response = $this->actingAs($this->adminUser)->get('/activity-logs?search=john');

        $response->assertOk();
    }

    public function test_activity_logs_export_requires_permission(): void
    {
        $response = $this->actingAs($this->supportUser)->post('/activity-logs/export', [
            'format' => 'csv',
        ]);

        $response->assertStatus(403);
    }

    public function test_activity_logs_export_works_for_admin(): void
    {
        $this->createLogEntry();

        $response = $this->actingAs($this->adminUser)->post('/activity-logs/export', [
            'format' => 'json',
        ]);

        $response->assertOk();
    }

    public function test_activity_logs_cleanup_requires_super_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->deleteJson('/activity-logs/cleanup', [
            'older_than_days' => 30,
        ]);

        $response->assertStatus(403);
    }

    public function test_activity_logs_cleanup_works_for_super_admin(): void
    {
        $this->createLogEntry(['created_at' => now()->subDays(60)]);

        $response = $this->actingAs($this->superAdmin)->deleteJson('/activity-logs/cleanup', [
            'older_than_days' => 30,
        ]);

        $response->assertOk();
    }
}
