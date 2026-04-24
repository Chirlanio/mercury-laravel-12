<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed da nova permission customer_vips.import (importar lista VIP via XLSX).
 *
 * Atribuída a SUPER_ADMIN, ADMIN e MARKETING. Migration idempotente — usa
 * updateOrCreate para a permission e syncWithoutDetaching para as atribuições.
 */
return new class extends Migration
{
    private const PERMISSION_SLUG = 'customer_vips.import';

    public function up(): void
    {
        $perm = Permission::IMPORT_VIP_CUSTOMERS;

        $centralPerm = CentralPermission::updateOrCreate(
            ['slug' => $perm->value],
            [
                'label' => $perm->label(),
                'description' => $perm->description(),
                'group' => 'customer_vips',
                'is_active' => true,
            ],
        );

        $eligibleRoles = ['super_admin', 'admin', 'marketing'];
        $roles = CentralRole::whereIn('name', $eligibleRoles)->get();

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching([$centralPerm->id]);
        }

        $this->clearCache();
    }

    public function down(): void
    {
        $centralPerm = CentralPermission::where('slug', self::PERMISSION_SLUG)->first();

        if ($centralPerm) {
            DB::table('central_role_permissions')
                ->where('central_permission_id', $centralPerm->id)
                ->delete();
            $centralPerm->delete();
        }

        $this->clearCache();
    }

    private function clearCache(): void
    {
        try {
            app(\App\Services\CentralRoleResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // Resolver pode não estar disponível em ambientes legacy.
        }
    }
};
