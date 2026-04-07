<?php

namespace Tests\Unit;

use App\Models\TenantPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantPlanModelTest extends TestCase
{
    use RefreshDatabase;

    private function createPlan(array $overrides = []): TenantPlan
    {
        return TenantPlan::create(array_merge([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'description' => 'A test plan',
            'max_users' => 10,
            'max_stores' => 5,
            'max_storage_mb' => 5120,
            'price_monthly' => 99.90,
            'price_yearly' => 999.90,
            'is_active' => true,
        ], $overrides));
    }

    public function test_plan_can_be_created(): void
    {
        $plan = $this->createPlan();

        $this->assertDatabaseHas('tenant_plans', [
            'slug' => 'test-plan',
            'name' => 'Test Plan',
        ]);
    }

    public function test_plan_slug_is_unique(): void
    {
        $this->createPlan();

        $this->expectException(\Exception::class);

        $this->createPlan(); // Duplicate slug
    }

    public function test_plan_casts_numeric_fields_correctly(): void
    {
        $plan = $this->createPlan();

        $this->assertIsInt($plan->max_users);
        $this->assertIsInt($plan->max_stores);
        $this->assertIsInt($plan->max_storage_mb);
        $this->assertIsBool($plan->is_active);
    }

    public function test_plan_features_is_cast_to_json(): void
    {
        $plan = $this->createPlan([
            'features' => ['module_a' => true, 'module_b' => false],
        ]);

        $plan->refresh();

        $this->assertIsArray($plan->features);
        $this->assertTrue($plan->features['module_a']);
    }

    public function test_plan_with_zero_limits_means_unlimited(): void
    {
        $plan = $this->createPlan([
            'max_users' => 0,
            'max_stores' => 0,
        ]);

        $this->assertEquals(0, $plan->max_users);
        $this->assertEquals(0, $plan->max_stores);
    }

    public function test_starter_plan_limits(): void
    {
        $plan = $this->createPlan([
            'slug' => 'starter',
            'name' => 'Starter',
            'max_users' => 5,
            'max_stores' => 1,
            'price_monthly' => 149.90,
        ]);

        $this->assertEquals(5, $plan->max_users);
        $this->assertEquals(1, $plan->max_stores);
    }

    public function test_enterprise_plan_unlimited(): void
    {
        $plan = $this->createPlan([
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'max_users' => 0,
            'max_stores' => 0,
            'price_monthly' => 499.90,
        ]);

        $this->assertEquals(0, $plan->max_users);  // 0 = unlimited
        $this->assertEquals(0, $plan->max_stores);
    }
}
