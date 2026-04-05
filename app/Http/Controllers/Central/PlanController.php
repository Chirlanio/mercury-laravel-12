<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\TenantModule;
use App\Models\TenantPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        $plans = TenantPlan::withCount('tenants')
            ->with('modules')
            ->orderBy('price_monthly')
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'max_users' => $plan->max_users,
                'max_stores' => $plan->max_stores,
                'max_storage_mb' => $plan->max_storage_mb,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'features' => $plan->features,
                'is_active' => $plan->is_active,
                'tenants_count' => $plan->tenants_count,
                'enabled_modules' => $plan->modules->where('is_enabled', true)->pluck('module_slug'),
                'created_at' => $plan->created_at->format('d/m/Y'),
            ]);

        $allModules = array_keys(config('modules', []));

        return Inertia::render('Central/Plans/Index', [
            'plans' => $plans,
            'allModules' => $allModules,
            'moduleLabels' => collect(config('modules', []))->mapWithKeys(fn ($m, $key) => [$key => $m['name']]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|alpha_dash|unique:tenant_plans,slug',
            'description' => 'nullable|string',
            'max_users' => 'required|integer|min:0',
            'max_stores' => 'required|integer|min:0',
            'max_storage_mb' => 'required|integer|min:0',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'features' => 'nullable|array',
            'modules' => 'required|array',
            'modules.*' => 'string',
        ]);

        $plan = TenantPlan::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'max_users' => $validated['max_users'],
            'max_stores' => $validated['max_stores'],
            'max_storage_mb' => $validated['max_storage_mb'],
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'features' => $validated['features'] ?? [],
            'is_active' => true,
        ]);

        $allModules = array_keys(config('modules', []));
        foreach ($allModules as $module) {
            TenantModule::create([
                'plan_id' => $plan->id,
                'module_slug' => $module,
                'is_enabled' => in_array($module, $validated['modules']),
            ]);
        }

        return redirect()->route('central.plans.index')
            ->with('success', "Plano '{$plan->name}' criado com sucesso.");
    }

    public function update(Request $request, TenantPlan $plan)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'max_users' => 'sometimes|integer|min:0',
            'max_stores' => 'sometimes|integer|min:0',
            'max_storage_mb' => 'sometimes|integer|min:0',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'modules' => 'sometimes|array',
            'modules.*' => 'string',
        ]);

        $modules = $validated['modules'] ?? null;
        unset($validated['modules']);

        $plan->update($validated);

        if ($modules !== null) {
            $allModules = array_keys(config('modules', []));
            foreach ($allModules as $module) {
                TenantModule::updateOrCreate(
                    ['plan_id' => $plan->id, 'module_slug' => $module],
                    ['is_enabled' => in_array($module, $modules)]
                );
            }
        }

        return back()->with('success', 'Plano atualizado com sucesso.');
    }

    public function destroy(TenantPlan $plan)
    {
        if ($plan->tenants()->exists()) {
            return back()->with('error', 'Não é possível excluir um plano com tenants vinculados.');
        }

        $plan->delete();

        return redirect()->route('central.plans.index')
            ->with('success', 'Plano excluído.');
    }
}
