<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Jobs\SyncCustomersFromCigamJob;
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
     * Últimas sincronizações executadas (todas as origens — manual,
     * schedule, CLI). Serve o painel "Histórico de Sincronizações" no
     * frontend. Retorna no máximo 30 registros.
     */
    public function syncHistory(Request $request): JsonResponse
    {
        $logs = CustomerSyncLog::query()
            ->with('startedBy:id,name')
            ->latest('started_at')
            ->limit(30)
            ->get();

        return response()->json([
            'logs' => $logs->map(fn (CustomerSyncLog $log) => [
                'id' => $log->id,
                'sync_type' => $log->sync_type,
                'status' => $log->status,
                'total_records' => (int) $log->total_records,
                'processed_records' => (int) $log->processed_records,
                'inserted_records' => (int) $log->inserted_records,
                'updated_records' => (int) $log->updated_records,
                'skipped_records' => (int) $log->skipped_records,
                'error_count' => (int) $log->error_count,
                'started_at' => $log->started_at?->toIso8601String(),
                'completed_at' => $log->completed_at?->toIso8601String(),
                'duration_seconds' => $log->duration_seconds,
                'started_by' => $log->startedBy?->name,
                'triggered' => $log->started_by_user_id ? 'manual' : 'schedule',
            ]),
        ]);
    }

    /**
     * Dispara um sync manual. A resposta HTTP volta IMEDIATAMENTE e
     * o trabalho roda em background via dispatchAfterResponse — o PHP
     * continua processando após terminar de enviar o response.
     *
     * Isso evita 2 problemas sérios:
     *  1. Browser pendurado esperando os ~50s do sync (UX ruim)
     *  2. PHP session lock bloqueando toda navegação do usuário
     *     durante o sync (o Laravel trava a session ao escrever nela)
     *
     * O progresso pode ser acompanhado em tempo real pelo modal
     * "Histórico de Sincronizações" (polling a cada 3s).
     *
     * Para syncs muito grandes (>50k), o CLI ainda é recomendado:
     *   php artisan customers:sync --chunk=2000
     */
    public function sync(Request $request, CustomerSyncService $service): RedirectResponse
    {
        if (! $service->isAvailable()) {
            return redirect()->back()->withErrors([
                'sync' => 'Conexão CIGAM indisponível. Verifique as credenciais CIGAM_DB_* em .env.',
            ]);
        }

        // Bloqueia múltiplos syncs paralelos — se já tem um em running,
        // devolve aviso em vez de criar outro log concorrente.
        $running = CustomerSyncLog::whereIn('status', ['pending', 'running'])->first();
        if ($running) {
            return redirect()->back()->with('warning', sprintf(
                'Já existe uma sincronização em andamento (Sync #%d). Acompanhe o progresso em "Histórico".',
                $running->id,
            ));
        }

        $log = $service->start('full', $request->user()->id);

        // dispatchAfterResponse — roda no mesmo processo PHP, mas
        // depois da resposta HTTP ter sido enviada ao cliente. Não
        // depende de queue worker externo.
        SyncCustomersFromCigamJob::dispatchAfterResponse($log->id, 1000);

        return redirect()->back()->with('success', sprintf(
            'Sincronização #%d iniciada em background. Acompanhe o progresso em "Histórico".',
            $log->id,
        ));
    }

    /**
     * Cancela um sync em andamento. O próximo processChunk vai detectar
     * o status e abortar graciosamente — nenhum `DELETE` é feito, logs
     * e dados já gravados permanecem.
     */
    public function cancelSync(int $log, CustomerSyncService $service): RedirectResponse
    {
        $service->cancel($log);

        return redirect()->back()->with('success', 'Sincronização cancelada. Registros já processados foram mantidos.');
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
