<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralActivityLog;
use App\Models\CentralModule;
use App\Models\TenantModule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = CentralModule::ordered()
            ->get()
            ->map(fn ($module) => [
                'id' => $module->id,
                'name' => $module->name,
                'slug' => $module->slug,
                'description' => $module->description,
                'icon' => $module->icon,
                'routes' => $module->routes,
                'dependencies' => $module->dependencies,
                'is_active' => $module->is_active,
                'sort_order' => $module->sort_order,
                'plans_count' => TenantModule::where('module_slug', $module->slug)
                    ->where('is_enabled', true)
                    ->distinct('plan_id')
                    ->count('plan_id'),
                'created_at' => $module->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Central/Modules/Index', [
            'modules' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|alpha_dash|unique:central_modules,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'routes' => 'nullable|array',
            'routes.*' => 'string',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'string',
        ]);

        $module = CentralModule::create([
            ...$validated,
            'is_active' => true,
            'sort_order' => (CentralModule::max('sort_order') ?? -1) + 1,
        ]);

        CentralActivityLog::log('module.created', "Módulo '{$module->name}' criado");

        return redirect('/admin/modules')
            ->with('success', "Módulo '{$module->name}' criado com sucesso.");
    }

    public function update(Request $request, CentralModule $module)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'routes' => 'nullable|array',
            'routes.*' => 'string',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'string',
            'is_active' => 'sometimes|boolean',
        ]);

        $module->update($validated);

        CentralActivityLog::log('module.updated', "Módulo '{$module->name}' atualizado");

        return back()->with('success', "Módulo '{$module->name}' atualizado.");
    }

    public function destroy(CentralModule $module)
    {
        $plansUsingModule = TenantModule::where('module_slug', $module->slug)
            ->where('is_enabled', true)
            ->distinct('plan_id')
            ->count('plan_id');

        if ($plansUsingModule > 0) {
            return back()->with('error', "Não é possível excluir: {$plansUsingModule} plano(s) utilizam este módulo.");
        }

        $name = $module->name;

        // Remove any disabled tenant_modules references
        TenantModule::where('module_slug', $module->slug)->delete();

        $module->delete();

        CentralActivityLog::log('module.deleted', "Módulo '{$name}' excluído");

        return redirect('/admin/modules')
            ->with('success', "Módulo '{$name}' excluído.");
    }
}
