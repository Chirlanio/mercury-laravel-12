<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralActivityLog;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use App\Services\CentralRoleResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RolePermissionController extends Controller
{
    public function __construct(
        protected CentralRoleResolver $resolver,
    ) {}

    public function index()
    {
        $roles = CentralRole::ordered()
            ->with('permissions')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
                'hierarchy_level' => $role->hierarchy_level,
                'is_system' => $role->is_system,
                'is_active' => $role->is_active,
                'permission_ids' => $role->permissions->pluck('id')->toArray(),
                'permissions_count' => $role->permissions->count(),
            ]);

        $permissions = CentralPermission::active()
            ->grouped()
            ->get()
            ->map(fn ($perm) => [
                'id' => $perm->id,
                'slug' => $perm->slug,
                'label' => $perm->label,
                'description' => $perm->description,
                'group' => $perm->group,
            ]);

        $permissionGroups = $permissions->groupBy('group')->map(fn ($group, $key) => [
            'name' => $key,
            'label' => $this->getGroupLabel($key),
            'permissions' => $group->values(),
        ])->values();

        return Inertia::render('Central/RolesPermissions/Index', [
            'roles' => $roles,
            'permissionGroups' => $permissionGroups,
        ]);
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|alpha_dash|unique:central_roles,name',
            'label' => 'required|string|max:100',
            'hierarchy_level' => 'required|integer|min:0|max:10',
        ]);

        $role = CentralRole::create([
            ...$validated,
            'is_system' => false,
            'is_active' => true,
        ]);

        CentralActivityLog::log('role.created', "Role '{$role->label}' criada");
        $this->resolver->clearCache();

        return back()->with('success', "Role '{$role->label}' criada.");
    }

    public function updateRole(Request $request, CentralRole $role)
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:100',
            'hierarchy_level' => 'sometimes|integer|min:0|max:10',
            'is_active' => 'sometimes|boolean',
        ]);

        $role->update($validated);

        CentralActivityLog::log('role.updated', "Role '{$role->label}' atualizada");
        $this->resolver->clearCache($role->name);

        return back()->with('success', "Role '{$role->label}' atualizada.");
    }

    public function destroyRole(CentralRole $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'Roles do sistema não podem ser excluídas.');
        }

        $label = $role->label;
        $role->permissions()->detach();
        $role->delete();

        CentralActivityLog::log('role.deleted', "Role '{$label}' excluída");
        $this->resolver->clearCache();

        return back()->with('success', "Role '{$label}' excluída.");
    }

    public function updateRolePermissions(Request $request, CentralRole $role)
    {
        $validated = $request->validate([
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'integer|exists:central_permissions,id',
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        CentralActivityLog::log(
            'role.permissions_updated',
            "Permissões da role '{$role->label}' atualizadas (" . count($validated['permission_ids']) . " permissões)"
        );

        $this->resolver->clearCache($role->name);

        return back()->with('success', "Permissões de '{$role->label}' atualizadas.");
    }

    protected function getGroupLabel(string $group): string
    {
        return match ($group) {
            'users' => 'Usuários',
            'profile' => 'Perfil',
            'dashboard' => 'Dashboard',
            'admin' => 'Administração',
            'support' => 'Suporte',
            'settings' => 'Configurações',
            'logs' => 'Logs',
            'system' => 'Sistema',
            'activity_logs' => 'Logs de Atividade',
            'system_settings' => 'Config. do Sistema',
            'sales' => 'Vendas',
            'products' => 'Produtos',
            'user_sessions' => 'Sessões',
            'transfers' => 'Transferências',
            'adjustments' => 'Ajustes de Estoque',
            'order_payments' => 'Ordens de Pagamento',
            'suppliers' => 'Fornecedores',
            'checklists' => 'Checklists',
            'medical_certificates' => 'Atestados Médicos',
            'absences' => 'Faltas',
            'overtime' => 'Horas Extras',
            'store_goals' => 'Metas de Loja',
            'movements' => 'Movimentações',
            default => ucfirst(str_replace('_', ' ', $group)),
        };
    }
}
