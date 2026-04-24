<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\CustomerVipTier;
use App\Services\CustomerVipClassificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listagem e curadoria de clientes VIP por ano.
 *
 * Respeita histórico: mudanças no ano corrente não apagam classificações de
 * anos anteriores (unique (customer_id, year) + updateOrCreate preservam).
 *
 * Rotas (tenant-routes.php, middleware tenant.module:customers + permissions):
 *   GET    /customers/vip                       index
 *   POST   /customers/vip/suggestions           runSuggestions  (MANAGE)
 *   PATCH  /customers/vip/{vip}                 curate          (CURATE)
 *   DELETE /customers/vip/{vip}                 destroy         (CURATE)
 */
class CustomerVipController extends Controller
{
    public function __construct(private readonly CustomerVipClassificationService $classifier) {}

    public function index(Request $request): Response
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $finalTierFilter = $request->input('final_tier'); // null|black|gold|pending
        $search = $request->input('search');

        $storeNames = \App\Models\Store::query()
            ->pluck('name', 'code')
            ->all();

        $query = CustomerVipTier::query()
            ->with(['customer:id,cigam_code,name,cpf,mobile,email,city,state', 'curatedBy:id,name'])
            ->forYear($year)
            ->orderByDesc('total_revenue');

        if ($finalTierFilter === 'pending') {
            $query->pendingCuration();
        } elseif (in_array($finalTierFilter, [CustomerVipTier::TIER_BLACK, CustomerVipTier::TIER_GOLD], true)) {
            $query->where('final_tier', $finalTierFilter);
        }

        if ($search) {
            $query->whereHas('customer', fn ($q) => $q->search($search));
        }

        $tiers = $query->paginate(25)
            ->withQueryString()
            ->through(fn (CustomerVipTier $t) => $this->formatTier($t, $storeNames));

        $stats = $this->computeStats($year);

        $availableYears = CustomerVipTier::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->values();

        // Garante que o ano corrente aparece no seletor mesmo sem dados ainda.
        if (! $availableYears->contains(now()->year)) {
            $availableYears = $availableYears->prepend(now()->year)->values();
        }

        return Inertia::render('Customers/VipIndex', [
            'tiers' => $tiers,
            'year' => $year,
            'availableYears' => $availableYears,
            'filters' => $request->only(['year', 'final_tier', 'search']),
            'statistics' => $stats,
            'can' => [
                'manage' => $request->user()?->hasPermissionTo(Permission::MANAGE_VIP_CUSTOMERS->value) ?? false,
                'curate' => $request->user()?->hasPermissionTo(Permission::CURATE_VIP_CUSTOMERS->value) ?? false,
                'view_reports' => $request->user()?->hasPermissionTo(Permission::VIEW_VIP_REPORTS->value) ?? false,
                'manage_activities' => $request->user()?->hasPermissionTo(Permission::MANAGE_VIP_ACTIVITIES->value) ?? false,
                'manage_config' => $request->user()?->hasPermissionTo(Permission::MANAGE_VIP_TIER_CONFIG->value) ?? false,
            ],
        ]);
    }

    public function runSuggestions(Request $request): RedirectResponse
    {
        $year = (int) $request->input('year', now()->year);

        $redirect = redirect()->route('customers.vip.index', ['year' => $year]);

        // Pré-validação da régua: precisa ter Black E Gold cadastrados.
        $tiersCadastrados = \App\Models\CustomerVipTierConfig::forYear($year)
            ->pluck('tier')
            ->all();
        $faltantes = array_diff(['black', 'gold'], $tiersCadastrados);

        if (! empty($faltantes)) {
            $msg = empty($tiersCadastrados)
                ? sprintf(
                    'Cadastre os limites Black e Gold da Lista %d (apurada sobre faturamento %d) na página "Limites" antes de gerar sugestões.',
                    $year, $year - 1,
                )
                : sprintf(
                    'Régua incompleta para a Lista %d — falta cadastrar %s na página "Limites".',
                    $year,
                    implode(' e ', array_map('ucfirst', $faltantes)),
                );

            return $redirect->with('warning', $msg);
        }

        $summary = $this->classifier->generateSuggestions($year);

        if (! $summary['has_thresholds']) {
            // Defensivo — checagem anterior já cobre, mas garante consistência
            return $redirect->with('warning', sprintf(
                'Não foi possível processar a Lista %d. Verifique a régua na página "Limites".',
                $summary['year'],
            ));
        }

        $msg = sprintf(
            'Lista %d (faturamento %d): %d Black + %d Gold de %d clientes com compras Meia Sola.',
            $summary['year'],
            $summary['revenue_year'],
            $summary['suggested_black'],
            $summary['suggested_gold'],
            $summary['processed'],
        );

        if ($summary['below_threshold'] > 0) {
            $msg .= sprintf(' %d ficaram abaixo do threshold.', $summary['below_threshold']);
        }
        if ($summary['preserved_curated'] > 0) {
            $msg .= sprintf(' %d curadorias preservadas.', $summary['preserved_curated']);
        }
        if ($summary['removed_obsolete'] > 0) {
            $msg .= sprintf(' %d registros auto obsoletos removidos.', $summary['removed_obsolete']);
        }

        return $redirect->with('success', $msg);
    }

    public function curate(Request $request, CustomerVipTier $vip): RedirectResponse
    {
        $data = $request->validate([
            'final_tier' => ['nullable', 'in:black,gold'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->classifier->curate(
            $vip->customer,
            $vip->year,
            $data['final_tier'] ?? null,
            $data['notes'] ?? null,
            $request->user(),
        );

        return back()->with('success', 'Curadoria registrada.');
    }

    public function destroy(Request $request, CustomerVipTier $vip): RedirectResponse
    {
        $this->classifier->remove($vip->customer, $vip->year, $request->user());

        return back()->with('success', 'Cliente removido da lista VIP do ano.');
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function formatTier(CustomerVipTier $tier, array $storeNames = []): array
    {
        $preferredCode = $tier->preferred_store_code;
        // Fallback para registros gerados antes da migration de revenue_year
        $revenueYear = $tier->revenue_year ?? ($tier->year - 1);

        return [
            'id' => $tier->id,
            'year' => $tier->year,
            'revenue_year' => $revenueYear,
            'suggested_tier' => $tier->suggested_tier,
            'final_tier' => $tier->final_tier,
            'total_revenue' => (float) $tier->total_revenue,
            'total_orders' => (int) $tier->total_orders,
            'preferred_store' => $preferredCode ? [
                'code' => $preferredCode,
                'name' => $storeNames[$preferredCode] ?? $preferredCode,
            ] : null,
            'source' => $tier->source,
            'suggested_at' => $tier->suggested_at?->toIso8601String(),
            'curated_at' => $tier->curated_at?->toIso8601String(),
            'curated_by' => $tier->curatedBy?->name,
            'notes' => $tier->notes,
            'customer' => [
                'id' => $tier->customer->id,
                'cigam_code' => $tier->customer->cigam_code,
                'name' => $tier->customer->name,
                'formatted_cpf' => $tier->customer->formatted_cpf,
                'formatted_mobile' => $tier->customer->formatted_mobile,
                'email' => $tier->customer->email,
                'city' => $tier->customer->city,
                'state' => $tier->customer->state,
            ],
        ];
    }

    private function computeStats(int $year): array
    {
        $base = CustomerVipTier::query()->forYear($year);

        return [
            'year' => $year,
            'total_black' => (clone $base)->where('final_tier', CustomerVipTier::TIER_BLACK)->count(),
            'total_gold' => (clone $base)->where('final_tier', CustomerVipTier::TIER_GOLD)->count(),
            'total_pending' => (clone $base)->pendingCuration()->count(),
            'total_revenue' => (float) (clone $base)->active()->sum('total_revenue'),
        ];
    }
}
