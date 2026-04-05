<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Models\TenantPlan;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'inactive_tenants' => Tenant::where('is_active', false)->count(),
            'trialing_tenants' => Tenant::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'total_plans' => TenantPlan::where('is_active', true)->count(),
            'pending_invoices' => TenantInvoice::where('status', 'pending')->count(),
            'overdue_invoices' => TenantInvoice::where('status', 'pending')
                ->where('due_at', '<', now())
                ->count(),
            'monthly_revenue' => TenantInvoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];

        $recentTenants = Tenant::with('plan', 'domains')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'domain' => $t->domains->first()?->domain,
                'plan' => $t->plan?->name ?? 'Nenhum',
                'is_active' => $t->is_active,
                'created_at' => $t->created_at->format('d/m/Y'),
            ]);

        $planDistribution = TenantPlan::withCount('tenants')
            ->where('is_active', true)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'count' => $p->tenants_count,
            ]);

        return Inertia::render('Central/Dashboard', [
            'stats' => $stats,
            'recentTenants' => $recentTenants,
            'planDistribution' => $planDistribution,
        ]);
    }
}
