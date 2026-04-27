<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\RelocationItemReason;
use App\Enums\RelocationPriority;
use App\Enums\RelocationStatus;
use App\Http\Requests\Relocation\StoreRelocationRequest;
use App\Http\Requests\Relocation\TransitionRelocationRequest;
use App\Http\Requests\Relocation\UpdateRelocationRequest;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationType;
use App\Models\Store;
use App\Models\User;
use App\Services\RelocationExportService;
use App\Services\RelocationImportService;
use App\Services\RelocationService;
use App\Services\RelocationSuggestionService;
use App\Services\RelocationTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RelocationController extends Controller
{
    public function __construct(
        private RelocationService $service,
        private RelocationTransitionService $transitionService,
        private RelocationSuggestionService $suggestionService,
        private RelocationExportService $exportService,
        private RelocationImportService $importService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreId = $this->resolveScopedStoreId($user);

        $query = $this->buildFilteredQuery($request)
            ->with([
                'type',
                'originStore:id,code,name',
                'destinationStore:id,code,name',
                'createdBy:id,name',
                'transfer:id,status,invoice_number',
            ])
            ->withCount('items');

        $relocations = $query->paginate(15)
            ->withQueryString()
            ->through(fn ($r) => $this->formatRelocation($r));

        $statistics = $this->buildStatistics($scopedStoreId);

        return Inertia::render('Relocations/Index', [
            'relocations' => $relocations,
            'filters' => $request->only([
                'origin_store_id', 'destination_store_id', 'status', 'relocation_type_id',
                'priority', 'search', 'date_from', 'date_to', 'include_terminal',
            ]),
            'statistics' => $statistics,
            'statusOptions' => RelocationStatus::labels(),
            'statusColors' => RelocationStatus::colors(),
            'statusTransitions' => RelocationStatus::transitionMap(),
            'priorityOptions' => RelocationPriority::labels(),
            'reasonOptions' => RelocationItemReason::labels(),
            'isStoreScoped' => $scopedStoreId !== null,
            'scopedStoreId' => $scopedStoreId,
            'permissions' => [
                'create' => $user->hasPermissionTo(Permission::CREATE_RELOCATIONS->value),
                'edit' => $user->hasPermissionTo(Permission::EDIT_RELOCATIONS->value),
                'delete' => $user->hasPermissionTo(Permission::DELETE_RELOCATIONS->value),
                'approve' => $user->hasPermissionTo(Permission::APPROVE_RELOCATIONS->value),
                'separate' => $user->hasPermissionTo(Permission::SEPARATE_RELOCATIONS->value),
                'receive' => $user->hasPermissionTo(Permission::RECEIVE_RELOCATIONS->value),
                'manage' => $user->hasPermissionTo(Permission::MANAGE_RELOCATIONS->value),
                'export' => $user->hasPermissionTo(Permission::EXPORT_RELOCATIONS->value),
                'import' => $user->hasPermissionTo(Permission::IMPORT_RELOCATIONS->value),
            ],
            'selects' => [
                'stores' => Store::orderBy('name')->get(['id', 'code', 'name']),
                'types' => RelocationType::active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            ],
        ]);
    }

    public function store(StoreRelocationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $scopedStoreId = $this->resolveScopedStoreId($user);

        $data = $request->validated();

        // Usuário com scoping fixa origin como sua própria loja (não permite
        // remanejos onde sua loja não é envolvida).
        if ($scopedStoreId) {
            if ($data['origin_store_id'] !== $scopedStoreId
                && $data['destination_store_id'] !== $scopedStoreId) {
                return redirect()->back()->withErrors([
                    'origin_store_id' => 'Você só pode criar remanejos envolvendo sua loja.',
                ])->withInput();
            }
        }

        try {
            $this->service->create($data, $user);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Remanejo criado.');
    }

    public function show(Relocation $relocation, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $relocation);

        $relocation->load([
            'type',
            'originStore:id,code,name',
            'destinationStore:id,code,name',
            'transfer',
            'items.product:id,reference',
            'createdBy:id,name',
            'approvedBy:id,name',
            'separatedBy:id,name',
            'receivedBy:id,name',
            'updatedBy:id,name',
            'deletedBy:id,name',
            'statusHistory.changedBy:id,name',
        ]);

        return response()->json([
            'relocation' => $this->formatRelocationDetailed($relocation),
        ]);
    }

    public function update(Relocation $relocation, UpdateRelocationRequest $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $relocation);

        try {
            $this->service->update($relocation, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Remanejo atualizado.');
    }

    public function destroy(Relocation $relocation, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $relocation);

        $data = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $this->service->softDelete($relocation, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Remanejo excluído.');
    }

    /**
     * Endpoint único de transição. O TransitionRequest valida formato base;
     * o Service valida regras de negócio (NF obrigatória, recebimento, etc).
     */
    public function transition(Relocation $relocation, TransitionRelocationRequest $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $relocation);

        $data = $request->validated();

        // Antes da transição em si, aplica qty_separated nos itens (quando
        // o frontend envia) — separação é mutação de itens, não de status.
        if (! empty($data['separated_items']) && is_array($data['separated_items'])) {
            $this->applySeparatedQuantities($relocation, $data['separated_items']);
        }

        try {
            $this->transitionService->transition(
                $relocation,
                $data['to_status'],
                $request->user(),
                $data['note'] ?? null,
                [
                    'invoice_number' => $data['invoice_number'] ?? null,
                    'invoice_date' => $data['invoice_date'] ?? null,
                    'volumes_qty' => $data['volumes_qty'] ?? null,
                    'receiver_name' => $data['receiver_name'] ?? null,
                    'received_items' => $data['received_items'] ?? [],
                ]
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Status do remanejo atualizado.');
    }

    public function statistics(Request $request): JsonResponse
    {
        $scopedStoreId = $this->resolveScopedStoreId($request->user());

        return response()->json($this->buildStatistics($scopedStoreId));
    }

    // ------------------------------------------------------------------
    // Export (XLSX 2 abas + PDF Romaneio)
    // ------------------------------------------------------------------

    public function export(Request $request): BinaryFileResponse
    {
        $query = $this->buildFilteredQuery($request);
        return $this->exportService->exportExcel($query);
    }

    public function exportRomaneio(Relocation $relocation, Request $request): HttpResponse
    {
        $this->ensureCanView($request->user(), $relocation);
        return $this->exportService->exportRomaneioPdf($relocation);
    }

    // ------------------------------------------------------------------
    // Import (XLSX preview + persist)
    // ------------------------------------------------------------------

    public function importPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                // mimetypes cobre upload em browsers/SOs onde o CSV vem como
                // application/vnd.ms-excel, text/plain, octet-stream etc.
                // Combinado com `mimes` valida tanto extensão quanto mime real.
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain,application/octet-stream,application/csv',
            ],
        ]);

        try {
            $result = $this->importService->preview($data['file']->getRealPath());
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Falha ao ler arquivo: '.$e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * Gera planilha modelo (CSV `;` v1-compatível) para download. 2 modos:
     *  - barcode: 4 colunas, match via product_variants.barcode/aux_reference
     *  - reference: 5 colunas (formato v1), match via reference + size.name
     *
     * Linhas de exemplo são puxadas do catálogo real do tenant — quando há
     * produtos sincronizados, o user pode importar o arquivo direto pra
     * testar. Sem produtos, cai num exemplo genérico.
     */
    public function importTemplate(Request $request): HttpResponse
    {
        $mode = $request->query('mode') === 'barcode' ? 'barcode' : 'reference';

        $stores = Store::orderBy('code')->take(2)->pluck('code')->toArray();
        $origin = $stores[0] ?? 'Z424';
        $destination = $stores[1] ?? 'Z423';

        $lines = [];

        if ($mode === 'barcode') {
            $lines[] = 'Origem;Destino;Codigo_Barras;Quantidade';

            $samples = \DB::table('product_variants')
                ->whereNotNull('barcode')
                ->where('barcode', '!=', '')
                ->where('is_active', 1)
                ->take(3)
                ->pluck('barcode');

            if ($samples->isEmpty()) {
                $samples = collect(['7891234567890', '7891234567891', '7891234567892']);
            }

            foreach ($samples as $i => $bc) {
                $qty = [5, 3, 8][$i] ?? 1;
                $lines[] = "{$origin};{$destination};{$bc};{$qty}";
            }
            $filename = 'modelo_remanejo_codigo_barras.csv';
        } else {
            $lines[] = 'Origem;Destino;Referencia;Tamanho;Quantidade';

            $samples = \DB::table('product_variants as pv')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->join('product_sizes as ps', 'ps.cigam_code', '=', 'pv.size_cigam_code')
                ->where('pv.is_active', 1)
                ->select('p.reference', 'ps.name as size_name')
                ->take(4)
                ->get();

            if ($samples->isEmpty()) {
                $samples = collect([
                    (object) ['reference' => 'REF001', 'size_name' => '34'],
                    (object) ['reference' => 'REF001', 'size_name' => '36'],
                    (object) ['reference' => 'REF002', 'size_name' => '38'],
                    (object) ['reference' => 'REF003', 'size_name' => 'M'],
                ]);
            }

            foreach ($samples as $i => $row) {
                $qty = [5, 10, 8, 3][$i] ?? 1;
                $lines[] = "{$origin};{$destination};{$row->reference};{$row->size_name};{$qty}";
            }
            $filename = 'modelo_remanejo_referencia.csv';
        }

        // BOM UTF-8 garante que Excel/Calc abra com acentos corretos
        $body = "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                // mimetypes cobre upload em browsers/SOs onde o CSV vem como
                // application/vnd.ms-excel, text/plain, octet-stream etc.
                // Combinado com `mimes` valida tanto extensão quanto mime real.
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain,application/octet-stream,application/csv',
            ],
        ]);

        try {
            $result = $this->importService->import($data['file']->getRealPath(), $request->user());
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors([
                'file' => 'Falha no import: '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('relocations.index')
            ->with('success', sprintf(
                'Import concluído: %d remanejo(s) com %d item(ns) criado(s).',
                $result['created'],
                $result['items_created'],
            ));
    }

    /**
     * Endpoint de sugestões. Consultado pelo painel "Gerar sugestões" do
     * CreateModal. Top N produtos vendidos na loja destino com sugestão
     * de origem por estimativa de saldo.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'destination_store_id' => 'required|integer|exists:stores,id',
            'days' => 'nullable|integer|min:7|max:180',
            'top' => 'nullable|integer|min:1|max:50',
        ]);

        // Scoping: usuário sem MANAGE só pode pedir sugestões pra própria loja
        $scopedStoreId = $this->resolveScopedStoreId($request->user());
        if ($scopedStoreId && $scopedStoreId !== (int) $data['destination_store_id']) {
            abort(403, 'Você só pode gerar sugestões para sua loja.');
        }

        $result = $this->suggestionService->suggestForStore(
            (int) $data['destination_store_id'],
            (int) ($data['days'] ?? 30),
            (int) ($data['top'] ?? 20),
        );

        return response()->json($result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Query base centralizada — usada pela listagem e pelo export.
     * Garante paridade absoluta dos filtros entre tela e XLSX.
     */
    protected function buildFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $scopedStoreId = $this->resolveScopedStoreId($request->user());

        $query = Relocation::query()->notDeleted()->latest();

        if ($scopedStoreId) {
            $query->forStore($scopedStoreId);
        } else {
            if ($request->filled('origin_store_id')) {
                $query->forOriginStore((int) $request->origin_store_id);
            }
            if ($request->filled('destination_store_id')) {
                $query->forDestinationStore((int) $request->destination_store_id);
            }
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_terminal')) {
            $query->whereNotIn('status', [
                RelocationStatus::COMPLETED->value,
                RelocationStatus::PARTIAL->value,
                RelocationStatus::REJECTED->value,
                RelocationStatus::CANCELLED->value,
            ]);
        }

        if ($request->filled('relocation_type_id')) {
            $query->where('relocation_type_id', $request->relocation_type_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('observations', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    /**
     * Aplica qty_separated em itens. Validação: o item precisa pertencer
     * ao remanejo + qty_separated não pode exceder qty_requested.
     */
    protected function applySeparatedQuantities(Relocation $relocation, array $items): void
    {
        $byId = [];
        foreach ($items as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $byId[$id] = (int) ($row['qty_separated'] ?? 0);
        }

        if (empty($byId)) {
            return;
        }

        $existing = RelocationItem::query()
            ->where('relocation_id', $relocation->id)
            ->whereIn('id', array_keys($byId))
            ->get();

        foreach ($existing as $item) {
            $qty = max(0, $byId[$item->id]);
            if ($qty > $item->qty_requested) {
                throw ValidationException::withMessages([
                    'separated_items' => "Quantidade separada do item {$item->product_reference} não pode exceder a solicitada.",
                ]);
            }
            $item->qty_separated = $qty;
            $item->save();
        }
    }

    /**
     * Sem MANAGE_RELOCATIONS, usuário fica restrito a remanejos onde sua
     * loja seja origem ou destino (filtro bilateral via scopeForStore).
     * `user.store_id` aqui referencia stores.code (string Z421...) — vamos
     * traduzir pra stores.id internamente.
     */
    protected function resolveScopedStoreId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_RELOCATIONS->value)) {
            return null;
        }

        if (! $user->store_id) {
            return null;
        }

        // user.store_id é string com o code da loja (ex: 'Z421').
        return Store::where('code', $user->store_id)->value('id');
    }

    protected function ensureCanView(?User $user, Relocation $relocation): void
    {
        $scopedId = $this->resolveScopedStoreId($user);
        if (! $scopedId) {
            return;
        }

        if ($relocation->origin_store_id !== $scopedId
            && $relocation->destination_store_id !== $scopedId) {
            abort(403, 'Você não tem acesso a remanejos de outras lojas.');
        }
    }

    protected function buildStatistics(?int $scopedStoreId): array
    {
        $base = Relocation::notDeleted();
        if ($scopedStoreId) {
            $base->forStore($scopedStoreId);
        }

        $total = (clone $base)->count();

        // Alias `status_value` evita o cast automático do model.
        $byStatus = (clone $base)
            ->selectRaw('status as status_value, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status_value');

        return [
            'total' => $total,
            'draft' => $byStatus->get(RelocationStatus::DRAFT->value)?->count ?? 0,
            'requested' => $byStatus->get(RelocationStatus::REQUESTED->value)?->count ?? 0,
            'approved' => $byStatus->get(RelocationStatus::APPROVED->value)?->count ?? 0,
            'in_separation' => $byStatus->get(RelocationStatus::IN_SEPARATION->value)?->count ?? 0,
            'in_transit' => $byStatus->get(RelocationStatus::IN_TRANSIT->value)?->count ?? 0,
            'completed' => $byStatus->get(RelocationStatus::COMPLETED->value)?->count ?? 0,
            'partial' => $byStatus->get(RelocationStatus::PARTIAL->value)?->count ?? 0,
            'overdue' => (clone $base)->overdue()->count(),
        ];
    }

    protected function formatRelocation(Relocation $r): array
    {
        return [
            'id' => $r->id,
            'ulid' => $r->ulid,
            'title' => $r->title,
            'type_name' => $r->type?->name,
            'origin_store' => $r->originStore ? [
                'id' => $r->originStore->id,
                'code' => $r->originStore->code,
                'name' => $r->originStore->name,
            ] : null,
            'destination_store' => $r->destinationStore ? [
                'id' => $r->destinationStore->id,
                'code' => $r->destinationStore->code,
                'name' => $r->destinationStore->name,
            ] : null,
            'priority' => $r->priority?->value,
            'priority_label' => $r->priority?->label(),
            'priority_color' => $r->priority?->color(),
            'status' => $r->status?->value,
            'status_label' => $r->status?->label(),
            'status_color' => $r->status?->color(),
            'deadline_days' => $r->deadline_days,
            'invoice_number' => $r->invoice_number,
            'transfer_id' => $r->transfer_id,
            'items_count' => $r->items_count ?? $r->items()->count(),
            'cigam_dispatched_at' => $r->cigam_dispatched_at?->toDateTimeString(),
            'cigam_received_at' => $r->cigam_received_at?->toDateTimeString(),
            'created_at' => $r->created_at?->toDateTimeString(),
            'created_by_name' => $r->createdBy?->name,
            'is_terminal' => $r->isTerminal(),
            'is_pre_transit' => $r->isPreTransit(),
            'allowed_transitions' => array_map(
                fn (RelocationStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $r->status?->allowedTransitions() ?? []
            ),
        ];
    }

    protected function formatRelocationDetailed(Relocation $r): array
    {
        return array_merge($this->formatRelocation($r), [
            'observations' => $r->observations,
            'requested_at' => $r->requested_at?->toDateTimeString(),
            'approved_at' => $r->approved_at?->toDateTimeString(),
            'separated_at' => $r->separated_at?->toDateTimeString(),
            'in_transit_at' => $r->in_transit_at?->toDateTimeString(),
            'completed_at' => $r->completed_at?->toDateTimeString(),
            'rejected_at' => $r->rejected_at?->toDateTimeString(),
            'rejected_reason' => $r->rejected_reason,
            'cancelled_at' => $r->cancelled_at?->toDateTimeString(),
            'cancelled_reason' => $r->cancelled_reason,
            'invoice_date' => $r->invoice_date?->toDateString(),
            'helpdesk_ticket_id' => $r->helpdesk_ticket_id,
            'transfer' => $r->transfer ? [
                'id' => $r->transfer->id,
                'status' => $r->transfer->status,
                'invoice_number' => $r->transfer->invoice_number,
                'transfer_type' => $r->transfer->transfer_type,
                'pickup_date' => $r->transfer->pickup_date?->toDateString(),
            ] : null,
            'fulfillment_percentage' => $r->fulfillment_percentage,
            'total_requested' => $r->total_requested,
            'total_received' => $r->total_received,
            'approved_by_name' => $r->approvedBy?->name,
            'separated_by_name' => $r->separatedBy?->name,
            'received_by_name' => $r->receivedBy?->name,
            'updated_by_name' => $r->updatedBy?->name,
            'deleted_by_name' => $r->deletedBy?->name,
            'items' => $r->items->map(fn (RelocationItem $i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product_reference' => $i->product_reference,
                'product_name' => $i->product_name,
                'product_color' => $i->product_color,
                'size' => $i->size,
                'barcode' => $i->barcode,
                'qty_requested' => $i->qty_requested,
                'qty_separated' => $i->qty_separated,
                'qty_received' => $i->qty_received,
                'dispatched_quantity' => $i->dispatched_quantity,
                'received_quantity' => $i->received_quantity,
                'dispatch_adherence' => $i->dispatch_adherence,
                'reason_code' => $i->reason_code,
                'observations' => $i->observations,
                'item_status' => $i->item_status,
            ])->values(),
            'status_history' => $r->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status?->value,
                'to_status' => $h->to_status->value,
                'from_status_label' => $h->from_status?->label(),
                'to_status_label' => $h->to_status->label(),
                'note' => $h->note,
                'changed_by_name' => $h->changedBy?->name,
                'created_at' => $h->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }
}
