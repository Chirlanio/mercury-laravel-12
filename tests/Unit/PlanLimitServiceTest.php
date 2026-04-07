<?php

namespace Tests\Unit;

use App\Models\TenantPlan;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PlanLimitServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected PlanLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->service = new PlanLimitService();
    }

    public function test_can_create_user_returns_true_without_tenant(): void
    {
        // No tenant context = no limits
        $this->assertTrue($this->service->canCreateUser(null));
    }

    public function test_can_create_store_returns_true_without_tenant(): void
    {
        $this->assertTrue($this->service->canCreateStore(null));
    }

    public function test_get_usage_returns_correct_structure(): void
    {
        $usage = $this->service->getUsage(null);

        $this->assertArrayHasKey('users', $usage);
        $this->assertArrayHasKey('stores', $usage);

        $this->assertArrayHasKey('current', $usage['users']);
        $this->assertArrayHasKey('max', $usage['users']);
        $this->assertArrayHasKey('unlimited', $usage['users']);
        $this->assertArrayHasKey('percentage', $usage['users']);
    }

    public function test_get_usage_reports_current_user_count(): void
    {
        $usage = $this->service->getUsage(null);

        // TestHelpers creates 3 users (admin, support, regular)
        $this->assertEquals(3, $usage['users']['current']);
    }
}
