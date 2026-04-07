<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\TenantPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_uses_string_key_type(): void
    {
        $tenant = new Tenant();

        $this->assertFalse($tenant->getIncrementing());
        $this->assertEquals('string', $tenant->getKeyType());
    }

    public function test_tenant_custom_columns_includes_required_fields(): void
    {
        $columns = Tenant::getCustomColumns();

        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('slug', $columns);
        $this->assertContains('plan_id', $columns);
        $this->assertContains('is_active', $columns);
        $this->assertContains('owner_name', $columns);
        $this->assertContains('owner_email', $columns);
    }

    public function test_tenant_is_trialing_returns_true_when_trial_not_expired(): void
    {
        $tenant = new Tenant();
        $tenant->trial_ends_at = now()->addDays(10);

        $this->assertTrue($tenant->isTrialing());
    }

    public function test_tenant_is_trialing_returns_false_when_expired(): void
    {
        $tenant = new Tenant();
        $tenant->trial_ends_at = now()->subDay();

        $this->assertFalse($tenant->isTrialing());
    }

    public function test_tenant_is_expired_returns_true_when_trial_past_and_no_plan(): void
    {
        $tenant = new Tenant();
        $tenant->trial_ends_at = now()->subDay();
        $tenant->plan_id = null;

        $this->assertTrue($tenant->isExpired());
    }

    public function test_tenant_is_expired_returns_false_when_has_plan(): void
    {
        $tenant = new Tenant();
        $tenant->trial_ends_at = now()->subDay();
        $tenant->plan_id = 1;

        $this->assertFalse($tenant->isExpired());
    }

    public function test_tenant_is_expired_returns_false_when_no_trial(): void
    {
        $tenant = new Tenant();
        $tenant->trial_ends_at = null;
        $tenant->plan_id = null;

        $this->assertFalse($tenant->isExpired());
    }

    public function test_tenant_has_module_returns_false_without_plan(): void
    {
        $tenant = new Tenant();
        $tenant->plan_id = null;

        $this->assertFalse($tenant->hasModule('sales'));
    }

    public function test_tenant_settings_is_cast_to_json(): void
    {
        $tenant = new Tenant();
        $tenant->settings = ['key' => 'value'];

        $this->assertIsArray($tenant->settings);
        $this->assertEquals('value', $tenant->settings['key']);
    }

    public function test_tenant_is_active_is_cast_to_boolean(): void
    {
        $tenant = new Tenant();
        $tenant->is_active = 1;

        $this->assertTrue($tenant->is_active);
        $this->assertIsBool($tenant->is_active);
    }
}
