<?php

namespace Tests\Unit;

use App\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralUserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_all_permissions(): void
    {
        $user = new CentralUser(['role' => 'super_admin']);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->canManageTenants());
        $this->assertTrue($user->canManagePlans());
        $this->assertTrue($user->canManageCentralUsers());
    }

    public function test_admin_can_manage_tenants_but_not_plans(): void
    {
        $user = new CentralUser(['role' => 'admin']);

        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->canManageTenants());
        $this->assertFalse($user->canManagePlans());
        $this->assertFalse($user->canManageCentralUsers());
    }

    public function test_viewer_has_no_management_permissions(): void
    {
        $user = new CentralUser(['role' => 'viewer']);

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->canManageTenants());
        $this->assertFalse($user->canManagePlans());
        $this->assertFalse($user->canManageCentralUsers());
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $user = new CentralUser(['is_active' => 1]);

        $this->assertTrue($user->is_active);
        $this->assertIsBool($user->is_active);
    }
}
