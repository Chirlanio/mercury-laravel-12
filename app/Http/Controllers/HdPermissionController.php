<?php

namespace App\Http\Controllers;

use App\Models\HdDepartment;
use App\Models\HdPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HdPermissionController extends Controller
{
    public function index(Request $request)
    {
        $departments = HdDepartment::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']);

        $selectedDepartmentId = (int) $request->get('department_id', $departments->first()?->id);

        $permissions = collect();
        if ($selectedDepartmentId) {
            $permissions = HdPermission::where('department_id', $selectedDepartmentId)
                ->with('user:id,name,email')
                ->get()
                ->map(fn ($p) => [
                    'user_id' => $p->user_id,
                    'user_name' => $p->user?->name,
                    'user_email' => $p->user?->email,
                    'level' => $p->level,
                    'level_label' => $p->level === 'manager' ? 'Gerente' : 'Técnico',
                ])
                ->values();
        }

        // Users available to add (any user not yet assigned to this dept)
        $assignedUserIds = HdPermission::where('department_id', $selectedDepartmentId)
            ->pluck('user_id')
            ->toArray();

        $availableUsers = User::whereNotIn('id', $assignedUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Helpdesk/Permissions', [
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'permissions' => $permissions,
            'availableUsers' => $availableUsers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|integer|exists:hd_departments,id',
            'user_id' => 'required|integer|exists:users,id',
            'level' => 'required|string|in:technician,manager',
        ]);

        HdPermission::updateOrCreate(
            [
                'department_id' => $validated['department_id'],
                'user_id' => $validated['user_id'],
            ],
            ['level' => $validated['level']]
        );

        return back()->with('success', 'Permissão atribuída com sucesso.');
    }

    public function update(Request $request, int $departmentId, int $userId)
    {
        $validated = $request->validate([
            'level' => 'required|string|in:technician,manager',
        ]);

        $updated = HdPermission::where('department_id', $departmentId)
            ->where('user_id', $userId)
            ->update(['level' => $validated['level']]);

        abort_if($updated === 0, 404, 'Permissão não encontrada.');

        return back()->with('success', 'Permissão atualizada.');
    }

    public function destroy(int $departmentId, int $userId)
    {
        $deleted = DB::table('hd_permissions')
            ->where('department_id', $departmentId)
            ->where('user_id', $userId)
            ->delete();

        abort_if($deleted === 0, 404, 'Permissão não encontrada.');

        return back()->with('success', 'Permissão removida.');
    }
}
