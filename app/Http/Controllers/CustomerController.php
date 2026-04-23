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
     * Dispara um sync manual — INLINE em batch curto (~15s ou 5000
     * registros por click).
     *
     * Por que inline e não background:
     *  - `php artisan serve` no Windows é SINGLE-THREADED: um único
     *    worker atende todas as requests. Background via
     *    dispatchAfterResponse mantém o worker ocupado e bloqueia
     *    toda navegação até terminar.
     *  - Inline com batch curto garante worker liberado rápido,
     *    resposta em poucos segundos, usuário pode navegar, e o
     *    histórico reflete o progresso em tempo real.
     *
     * Como continua de onde parou: se houver log em 'running'/'pending'
     * (criado em clique anterior), retoma dele; senão cria log novo.
     * O cursor (último cigam_code) é inferido do Customer mais
     * recentemente sincronizado dentro desse log.
     *
     * Para syncs muito grandes (>10k), use CLI (sem time limit):
     *   php artisan customers:sync --chunk=2000
     */
    public function sync(Request $request, CustomerSyncService $service): RedirectResponse
    {
        if (! $service->isAvailable()) {
            return redirect()->back()->withErrors([
                'sync' => 'Conexão CIGAM indisponível. Verifique as credenciais CIGAM_DB_* em .env.',
            ]);
        }

        // Retoma log existente ou cria novo
        $log = CustomerSyncLog::whereIn('status', ['pending', 'running'])
            ->latest('id')
            ->first()
            ?? $service->start('full', $request->user()->id);

        // Cursor: último cigam_code processado dentro deste log.
        $lastCode = Customer::query()
            ->whereNotNull('synced_at')
            ->where('synced_at', '>=', $log->started_at)
            ->orderByDesc('cigam_code')
            ->value('cigam_code');

        // Limite por click: 15s OU 10 chunks de 500 = 5000 registros
        set_time_limit(30);
        $hardDeadline = microtime(true) + 15.0;
        $maxChunks = 10;

        $chunks = 0;
        $batchProcessed = 0;
        $batchInserted = 0;
        $batchUpdated = 0;
        $done = false;

        try {
            while (true) {
                $result = $service->processChunk($log->id, $lastCode, 500);
                $chunks++;
                $batchProcessed += $result['processed'];
                $batchInserted += $result['inserted'];
                $batchUpdated += $result['updated'];
                $lastCode = $result['last_code'];

                if (! $result['has_more'] || $result['cancelled']) {
                    $done = true;
                    break;
                }

                if ($chunks >= $maxChunks || microtime(true) >= $hardDeadline) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Customer sync batch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors([
                'sync' => 'Erro ao sincronizar: '.$e->getMessage(),
            ]);
        }

        $fresh = $log->fresh();

        if ($done) {
            return redirect()->back()->with('success', sprintf(
                'Sincronização #%d concluída. Total: %d processados, %d novos, %d atualizados.',
                $log->id,
                (int) $fresh->processed_records,
                (int) $fresh->inserted_records,
                (int) $fresh->updated_records,
            ));
        }

        $totalEstimate = (int) $fresh->total_records;
        $processed = (int) $fresh->processed_records;
        $pct = $totalEstimate > 0 ? round(($processed / $totalEstimate) * 100) : null;

        return redirect()->back()->with('warning', sprintf(
            'Progresso parcial — Sync #%d: %d/%d%s. Clique em "Sincronizar" novamente para continuar.',
            $log->id,
            $processed,
            $totalEstimate,
            $pct !== null ? " ({$pct}%)" : '',
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
