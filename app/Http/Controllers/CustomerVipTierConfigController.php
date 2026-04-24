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

    /**
     * Cadastra o par Black + Gold de um ano em uma única operação.
     * Endpoint preferido pelo modal "Cadastrar limites do ano" — evita o
     * fluxo confuso de 2 cadastros separados pra um único ano.
     *
     * Validação garante que Black >= Gold (régua mais alta = tier superior).
     */
    public function storeYear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'black_min_revenue' => ['required', 'numeric', 'min:0'],
            'gold_min_revenue' => ['required', 'numeric', 'min:0', 'lte:black_min_revenue'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'gold_min_revenue.lte' => 'O valor mínimo de Gold não pode ser maior que o de Black.',
        ]);

        CustomerVipTierConfig::updateOrCreate(
            ['year' => $data['year'], 'tier' => 'black'],
            ['min_revenue' => $data['black_min_revenue'], 'notes' => $data['notes'] ?? null],
        );

        CustomerVipTierConfig::updateOrCreate(
            ['year' => $data['year'], 'tier' => 'gold'],
            ['min_revenue' => $data['gold_min_revenue'], 'notes' => $data['notes'] ?? null],
        );

        return back()->with('success', sprintf(
            'Limites da Lista %d cadastrados (apurados sobre faturamento %d).',
            $data['year'],
            $data['year'] - 1,
        ));
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
