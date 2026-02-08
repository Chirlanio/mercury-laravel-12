<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // Register HOUR() function for SQLite compatibility
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('HOUR', function ($value) {
                return $value ? (int) date('G', strtotime($value)) : null;
            }, 1);
        }
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_is_displayed_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/dashboard');

        $response->assertOk();
    }

    public function test_dashboard_is_displayed_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/dashboard');

        $response->assertOk();
    }
}
