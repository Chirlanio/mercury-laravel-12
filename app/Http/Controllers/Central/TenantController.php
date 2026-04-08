<?php

namespace App\Http\Controllers\Central;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\CentralActivityLog;
use App\Models\Tenant;
use App\Models\TenantPlan;
use App\Services\TenantProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function __construct(
        protected TenantProvisioningService $provisioning,
    ) {}

    public function index(Request $request)
    {
        $query = Tenant::with('plan', 'domains');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('owner_email', 'like', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        if ($planId = $request->input('plan_id')) {
            $query->where('plan_id', $planId);
        }

        $tenants = $query->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'cnpj' => $t->cnpj,
                'domain' => $t->domains->first()?->domain,
                'plan' => $t->plan ? ['id' => $t->plan->id, 'name' => $t->plan->name, 'slug' => $t->plan->slug] : null,
                'is_active' => $t->is_active,
                'owner_name' => $t->owner_name,
                'owner_email' => $t->owner_email,
                'trial_ends_at' => $t->trial_ends_at?->format('d/m/Y'),
                'is_trialing' => $t->isTrialing(),
                'created_at' => $t->created_at->format('d/m/Y H:i'),
            ]);

        $plans = TenantPlan::where('is_active', true)->get(['id', 'name', 'slug']);

        return Inertia::render('Central/Tenants/Index', [
            'tenants' => $tenants,
            'plans' => $plans,
            'filters' => $request->only(['search', 'status', 'plan_id']),
        ]);
    }

    public function show(string $tenant)
    {
        $tenant = Tenant::with('plan', 'domains', 'invoices')->findOrFail($tenant);

        // Get usage stats by running queries in tenant context
        $usage = $this->getTenantUsage($tenant);

        return Inertia::render('Central/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'cnpj' => $tenant->cnpj,
                'domain' => $tenant->domains->first()?->domain,
                'plan' => $tenant->plan,
                'is_active' => $tenant->is_active,
                'owner_name' => $tenant->owner_name,
                'owner_email' => $tenant->owner_email,
                'settings' => $tenant->settings ?? [],
                'trial_ends_at' => $tenant->trial_ends_at?->format('d/m/Y'),
                'is_trialing' => $tenant->isTrialing(),
                'is_expired' => $tenant->isExpired(),
                'created_at' => $tenant->created_at->format('d/m/Y H:i'),
                'modules' => $tenant->activeModules()->pluck('module_slug'),
                'allowed_roles' => $tenant->getAllowedRoles(),
            ],
            'allRoles' => Role::options(),
            'usage' => $usage,
            'plans' => TenantPlan::where('is_active', true)->get(['id', 'name', 'slug', 'max_users', 'max_stores']),
            'recentInvoices' => $tenant->invoices()
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|alpha_dash|unique:tenants,slug',
            'cnpj' => 'nullable|string|max:18',
            'plan_id' => 'nullable|exists:tenant_plans,id',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255',
            'admin_password' => 'nullable|string|min:8',
            'trial_days' => 'nullable|integer|min:1|max:365',
        ]);

        $plan = $validated['plan_id']
            ? TenantPlan::find($validated['plan_id'])
            : null;

        try {
            $tenant = $this->provisioning->createTenant([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? Str::slug($validated['name']),
                'cnpj' => $validated['cnpj'],
                'plan_slug' => $plan?->slug,
                'owner_name' => $validated['owner_name'],
                'owner_email' => $validated['owner_email'],
                'admin_password' => $validated['admin_password'],
                'trial_days' => $validated['trial_days'],
            ]);

            CentralActivityLog::log('tenant.created', "Tenant '{$tenant->name}' criado", $tenant->id);

            return redirect("/admin/tenants/{$tenant->id}")
                ->with('success', "Tenant '{$tenant->name}' criado com sucesso.");
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar tenant: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function update(Request $request, string $tenant)
    {
        $tenant = Tenant::findOrFail($tenant);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'plan_id' => 'nullable|exists:tenant_plans,id',
            'owner_name' => 'sometimes|string|max:255',
            'owner_email' => 'sometimes|email|max:255',
            'settings' => 'nullable|array',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Tenant atualizado com sucesso.');
    }

    public function updateAllowedRoles(Request $request, string $tenant)
    {
        $tenant = Tenant::findOrFail($tenant);

        $validRoles = array_column(Role::cases(), 'value');

        $validated = $request->validate([
            'allowed_roles' => 'required|array|min:1',
            'allowed_roles.*' => 'string|in:' . implode(',', $validRoles),
        ]);

        $settings = $tenant->settings ?? [];
        $settings['allowed_roles'] = array_values($validated['allowed_roles']);
        $tenant->update(['settings' => $settings]);

        CentralActivityLog::log(
            'tenant.updated',
            "Roles permitidas atualizadas para '{$tenant->name}': " . implode(', ', $validated['allowed_roles']),
            $tenant->id
        );

        return back()->with('success', 'Roles permitidas atualizadas com sucesso.');
    }

    public function suspend(string $tenant)
    {
        $tenant = Tenant::findOrFail($tenant);
        $this->provisioning->suspendTenant($tenant);
        CentralActivityLog::log('tenant.suspended', "Tenant '{$tenant->name}' suspenso", $tenant->id);

        return back()->with('success', "Tenant '{$tenant->name}' suspenso.");
    }

    public function reactivate(string $tenant)
    {
        $tenant = Tenant::findOrFail($tenant);
        $this->provisioning->reactivateTenant($tenant);
        CentralActivityLog::log('tenant.reactivated', "Tenant '{$tenant->name}' reativado", $tenant->id);

        return back()->with('success', "Tenant '{$tenant->name}' reativado.");
    }

    public function destroy(string $tenant)
    {
        $tenant = Tenant::findOrFail($tenant);
        $name = $tenant->name;

        // Log BEFORE delete since FK constraint prevents logging after
        CentralActivityLog::log('tenant.deleted', "Tenant '{$name}' excluido permanentemente", $tenant->id);

        $this->provisioning->deleteTenant($tenant);

        return redirect('/admin/tenants')
            ->with('success', "Tenant '{$name}' excluído permanentemente.");
    }

    protected function getTenantUsage(Tenant $tenant): array
    {
        try {
            $usage = ['users' => 0, 'stores' => 0, 'employees' => 0];

            $tenant->run(function () use (&$usage) {
                $usage['users'] = \App\Models\User::count();
                $usage['stores'] = \App\Models\Store::count();
                $usage['employees'] = \App\Models\Employee::count();
            });

            return $usage;
        } catch (\Exception $e) {
            return ['users' => '?', 'stores' => '?', 'employees' => '?'];
        }
    }
}
