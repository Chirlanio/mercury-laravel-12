<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona a permissão `consignments.import` (Fase 6 — importação de
 * consignações históricas v1 via planilha XLSX/CSV) ao catálogo central
 * e vincula aos papéis que já tinham EXPORT_CONSIGNMENTS.
 *
 * Idempotente: updateOrCreate + syncWithoutDetaching.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = CentralPermission::updateOrCreate(
            ['slug' => Permission::IMPORT_CONSIGNMENTS->value],
            [
                'label' => Permission::IMPORT_CONSIGNMENTS->label(),
                'description' => Permission::IMPORT_CONSIGNMENTS->description(),
                'group' => 'consignments',
                'is_active' => true,
            ]
        );

        // Super admin + admin ganham a permissão (mesmo padrão do EXPORT)
        foreach ([Role::SUPER_ADMIN, Role::ADMIN] as $roleEnum) {
            $role = CentralRole::where('name', $roleEnum->value)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }
    }

    public function down(): void
    {
        $perm = CentralPermission::where('slug', Permission::IMPORT_CONSIGNMENTS->value)->first();
        if (! $perm) {
            return;
        }

        DB::table('central_role_permissions')
            ->where('central_permission_id', $perm->id)
            ->delete();

        $perm->delete();
    }
};
