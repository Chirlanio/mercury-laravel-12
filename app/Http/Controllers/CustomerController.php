<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\CustomerSyncLog;
use App\Services\CustomerSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller de Clientes. Majoritariamente read-only — escrita acontece
 * no CIGAM; Mercury só consulta e exibe.
 *
 *  - index: listagem com busca e filtros
 *  - show: JSON detalhado (usado em drill-down)
 *  - lookup: AJAX endpoint para autocomplete em outros módulos
 *    (Consignments usa para preencher recipient_* ao selecionar cliente)
 *  - sync: dispara sync manual (requer SYNC_CUSTOMERS)
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Customer::query()->latest('synced_at');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('state')) {
            $query->where('state', strtoupper($request->state));
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%'.strtoupper($request->city).'%');
        }

        if ($request->boolean('only_active', true)) {
            $query->active();
        }

        $customers = $query->paginate(20)
            ->withQueryString()
            ->through(fn (Customer $c) => $this->formatCustomer($c));

        $stats = [
            'total' => Customer::count(),
            'active' => Customer::active()->count(),
            'last_sync' => CustomerSyncLog::latest()->first()?->completed_at?->toIso8601String(),
        ];

        // Estados distintos para o filtro (lista curta, ok fazer no render)
        $states = Customer::query()
            ->whereNotNull('state')
            ->distinct()
            ->orderBy('state')
            ->pluck('state')
            ->values();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'state', 'city', 'only_active']),
            'statistics' => $stats,
            'states' => $states,
            'can' => [
                'export' => $request->user()?->hasPermissionTo(Permission::EXPORT_CUSTOMERS->value) ?? false,
                'sync' => $request->user()?->hasPermissionTo(Permission::SYNC_CUSTOMERS->value) ?? false,
            ],
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['consignments' => function ($q) {
            $q->latest()->limit(20);
        }]);

        return response()->json([
            'customer' => $this->formatCustomerDetailed($customer),
        ]);
    }

    /**
     * Autocomplete para outros módulos (ex: Consignments pre-preenche
     * recipient_* ao selecionar cliente). Aceita query mínima de 2
     * caracteres em name/cpf/email/mobile/cigam_code.
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($data['limit'] ?? 15);

        $customers = Customer::query()
            ->active()
            ->search($data['q'])
            ->orderBy('name')
            ->limit($limit)
            ->get([
                'id', 'cigam_code', 'name', 'cpf', 'email',
                'mobile', 'phone', 'city', 'state',
            ]);

        return response()->json([
            'results' => $customers->map(fn (Customer $c) => [
                'id' => $c->id,
                'cigam_code' => $c->cigam_code,
                'name' => $c->name,
                'cpf' => $c->cpf,
                'formatted_cpf' => $c->formatted_cpf,
                'email' => $c->email,
                'primary_contact' => $c->primary_contact,
                'formatted_mobile' => $c->formatted_mobile,
                'city' => $c->city,
                'state' => $c->state,
            ]),
        ]);
    }

    /**
     * Dispara um sync manual. O schedule diário (04:00) continua
     * rodando; este é para cenários de urgência pós-cadastro de cliente
     * novo no CIGAM.
     */
    public function sync(Request $request, CustomerSyncService $service): RedirectResponse
    {
        if (! $service->isAvailable()) {
            return redirect()->back()->withErrors([
                'sync' => 'Conexão CIGAM indisponível. Tente novamente em alguns minutos.',
            ]);
        }

        // Dispatch assíncrono via command (kickoff rápido — não espera terminar)
        \Illuminate\Support\Facades\Artisan::queue('customers:sync', [
            '--chunk' => 1000,
        ]);

        return redirect()->back()->with('success', 'Sincronização iniciada em background. Pode levar alguns minutos.');
    }

    // ==================================================================
    // Formatters
    // ==================================================================

    protected function formatCustomer(Customer $c): array
    {
        return [
            'id' => $c->id,
            'cigam_code' => $c->cigam_code,
            'name' => $c->name,
            'cpf' => $c->cpf,
            'formatted_cpf' => $c->formatted_cpf,
            'email' => $c->email,
            'primary_contact' => $c->primary_contact,
            'formatted_mobile' => $c->formatted_mobile,
            'city' => $c->city,
            'state' => $c->state,
            'is_active' => (bool) $c->is_active,
            'synced_at' => $c->synced_at?->toIso8601String(),
        ];
    }

    protected function formatCustomerDetailed(Customer $c): array
    {
        return array_merge($this->formatCustomer($c), [
            'person_type' => $c->person_type,
            'gender' => $c->gender,
            'phone' => $c->phone,
            'mobile' => $c->mobile,
            'address' => $c->address,
            'number' => $c->number,
            'complement' => $c->complement,
            'neighborhood' => $c->neighborhood,
            'zipcode' => $c->zipcode,
            'birth_date' => $c->birth_date?->format('Y-m-d'),
            'registered_at' => $c->registered_at?->format('Y-m-d'),
            'consignments' => $c->consignments->map(fn ($cons) => [
                'id' => $cons->id,
                'type' => $cons->type?->value,
                'type_label' => $cons->type?->label(),
                'status' => $cons->status?->value,
                'status_label' => $cons->status?->label(),
                'outbound_invoice_number' => $cons->outbound_invoice_number,
                'outbound_invoice_date' => $cons->outbound_invoice_date?->format('Y-m-d'),
                'outbound_total_value' => (float) $cons->outbound_total_value,
            ])->values(),
        ]);
    }
}
