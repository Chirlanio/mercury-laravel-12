<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\CustomerVipTierConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD dos thresholds anuais por tier VIP.
 *
 * Permission: MANAGE_VIP_TIER_CONFIG.
 */
class CustomerVipTierConfigController extends Controller
{
    public function index(Request $request): Response
    {
        $configs = CustomerVipTierConfig::query()
            ->orderByDesc('year')
            ->orderBy('tier')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (CustomerVipTierConfig $c) => [
                'id' => $c->id,
                'year' => (int) $c->year,
                'tier' => $c->tier,
                'min_revenue' => (float) $c->min_revenue,
                'notes' => $c->notes,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Customers/VipConfig', [
            'configs' => $configs,
            'can' => [
                'manage_config' => $request->user()?->hasPermissionTo(Permission::MANAGE_VIP_TIER_CONFIG->value) ?? false,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'tier' => ['required', 'in:black,gold'],
            'min_revenue' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        CustomerVipTierConfig::updateOrCreate(
            ['year' => $data['year'], 'tier' => $data['tier']],
            ['min_revenue' => $data['min_revenue'], 'notes' => $data['notes'] ?? null],
        );

        return back()->with('success', 'Threshold salvo.');
    }

    public function update(Request $request, CustomerVipTierConfig $config): RedirectResponse
    {
        $data = $request->validate([
            'min_revenue' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $config->update($data);

        return back()->with('success', 'Threshold atualizado.');
    }

    public function destroy(CustomerVipTierConfig $config): RedirectResponse
    {
        $config->delete();

        return back()->with('success', 'Threshold removido.');
    }
}
