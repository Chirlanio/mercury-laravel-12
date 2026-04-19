<?php

namespace App\Http\Controllers;

use App\Enums\BudgetUploadType;
use App\Http\Requests\Budget\ImportBudgetRequest;
use App\Http\Requests\Budget\PreviewBudgetRequest;
use App\Http\Requests\Budget\StoreBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetMetaRequest;
use App\Models\AccountingClass;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\Store;
use App\Services\BudgetFileStorageService;
use App\Services\BudgetImportService;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetService $service,
        private BudgetFileStorageService $storage,
        private BudgetImportService $importService,
    ) {}

    public function index(Request $request): Response
    {
        $query = BudgetUpload::query()
            ->with(['createdBy', 'updatedBy'])
            ->notDeleted();

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('year')) {
            $query->forYear($request->integer('year'));
        }

        if ($request->filled('scope_label')) {
            $query->forScope($request->string('scope_label')->toString());
        }

        if ($request->filled('upload_type')) {
            $query->where('upload_type', $request->string('upload_type')->toString());
        }

        // Default: só mostra versões ativas. Flag `include_inactive=1` libera todas.
        if (! $request->boolean('include_inactive')) {
            $query->active();
        }

        $budgets = $query
            ->orderByDesc('year')
            ->orderBy('scope_label')
            ->orderByDesc('major_version')
            ->orderByDesc('minor_version')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($b) => $this->format($b));

        return Inertia::render('Budgets/Index', [
            'budgets' => $budgets,
            'filters' => $request->only(['search', 'year', 'scope_label', 'upload_type', 'include_inactive']),
            'statistics' => $this->buildStatistics(),
            'enums' => [
                'uploadTypes' => BudgetUploadType::options(),
            ],
            'selects' => [
                'scopes' => BudgetUpload::query()
                    ->notDeleted()
                    ->select('scope_label')
                    ->distinct()
                    ->orderBy('scope_label')
                    ->pluck('scope_label'),
                'years' => BudgetUpload::query()
                    ->notDeleted()
                    ->select('year')
                    ->distinct()
                    ->orderByDesc('year')
                    ->pluck('year'),
                'accountingClasses' => AccountingClass::query()
                    ->notDeleted()
                    ->leaves()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
                'managementClasses' => ManagementClass::query()
                    ->notDeleted()
                    ->leaves()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
                'costCenters' => CostCenter::query()
                    ->notDeleted()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
                'stores' => Store::query()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
            ],
        ]);
    }

    public function statistics(): JsonResponse
    {
        return response()->json($this->buildStatistics());
    }

    public function show(BudgetUpload $budget): JsonResponse
    {
        if ($budget->isDeleted()) {
            return response()->json(['message' => 'Versão excluída.'], 404);
        }

        $budget->load([
            'items' => fn ($q) => $q->with([
                'accountingClass:id,code,name',
                'managementClass:id,code,name',
                'costCenter:id,code,name',
                'store:id,code,name',
            ]),
            'statusHistories.changedBy',
            'createdBy',
            'updatedBy',
        ]);

        return response()->json([
            'budget' => $this->format($budget, full: true),
        ]);
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        try {
            $upload = $this->service->create(
                $data,
                $items,
                $request->file('file'),
                $request->user()
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('budgets.index')
            ->with('success', "Orçamento {$upload->version_label} cadastrado ({$upload->items_count} linhas, total R$ ".number_format((float) $upload->total_year, 2, ',', '.').").");
    }

    public function update(UpdateBudgetMetaRequest $request, BudgetUpload $budget): RedirectResponse
    {
        try {
            $this->service->updateMeta($budget, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Observações atualizadas.');
    }

    public function destroy(Request $request, BudgetUpload $budget): RedirectResponse
    {
        $reason = (string) $request->input('deleted_reason', '');

        try {
            $this->service->delete($budget, $reason, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Versão excluída.');
    }

    /**
     * Passo 1 do upload — analisa o xlsx, retorna diagnóstico com
     * linhas válidas/pendentes/rejeitadas + sugestões fuzzy para
     * códigos ausentes. Não persiste nada.
     */
    public function preview(PreviewBudgetRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->preview(
                $request->file('file')->getRealPath()
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erro ao processar a planilha: '.$e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * Passo 2 do upload — recebe o arquivo + mapping de reconciliação
     * feito pelo usuário, produz items[] final e persiste via
     * BudgetService::create().
     */
    public function import(ImportBudgetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $mapping = $data['mapping'] ?? [];
        $file = $request->file('file');

        try {
            $resolution = $this->importService->resolveItems(
                $file->getRealPath(),
                $mapping
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'file' => 'Erro ao processar planilha: '.$e->getMessage(),
            ]);
        }

        if (empty($resolution['items'])) {
            return back()->withErrors([
                'file' => 'Nenhuma linha válida para importar. Verifique o mapping de códigos ausentes.',
            ]);
        }

        try {
            $upload = $this->service->create(
                [
                    'year' => $data['year'],
                    'scope_label' => $data['scope_label'],
                    'upload_type' => $data['upload_type'],
                    'notes' => $data['notes'] ?? null,
                ],
                $resolution['items'],
                $file,
                $request->user()
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $msg = sprintf(
            '%d linhas importadas (%d ignoradas) — v%s, total R$ %s',
            $resolution['stats']['imported'],
            $resolution['stats']['skipped'],
            $upload->version_label,
            number_format((float) $upload->total_year, 2, ',', '.')
        );

        return redirect()
            ->route('budgets.index')
            ->with('success', $msg)
            ->with('import_stats', $resolution['stats']);
    }

    /**
     * Download do xlsx original armazenado.
     */
    public function download(BudgetUpload $budget): HttpResponse
    {
        if ($budget->isDeleted()) {
            abort(404);
        }

        if (! $this->storage->exists($budget->stored_path)) {
            abort(404, 'Arquivo original não encontrado no storage.');
        }

        return $this->storage->downloadResponse(
            $budget->stored_path,
            $budget->original_filename
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function buildStatistics(): array
    {
        $base = BudgetUpload::query()->notDeleted();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->active()->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'distinct_scopes' => (clone $base)->distinct('scope_label')->count('scope_label'),
            'distinct_years' => (clone $base)->distinct('year')->count('year'),
            'total_amount_active' => (float) (clone $base)->active()->sum('total_year'),
        ];
    }

    protected function format(BudgetUpload $b, bool $full = false): array
    {
        $uploadType = $b->upload_type instanceof BudgetUploadType ? $b->upload_type : null;

        $payload = [
            'id' => $b->id,
            'year' => (int) $b->year,
            'scope_label' => $b->scope_label,
            'version_label' => $b->version_label,
            'major_version' => (int) $b->major_version,
            'minor_version' => (int) $b->minor_version,
            'upload_type' => $uploadType?->value ?? $b->upload_type,
            'upload_type_label' => $uploadType?->label(),
            'original_filename' => $b->original_filename,
            'file_size_bytes' => $b->file_size_bytes,
            'is_active' => (bool) $b->is_active,
            'notes' => $b->notes,
            'total_year' => (float) $b->total_year,
            'items_count' => (int) $b->items_count,
            'created_at' => $b->created_at?->toIso8601String(),
            'updated_at' => $b->updated_at?->toIso8601String(),
            'created_by' => $b->createdBy?->name,
            'updated_by' => $b->updatedBy?->name,
        ];

        if ($full) {
            $payload['items'] = $b->items?->map(fn ($i) => [
                'id' => $i->id,
                'accounting_class' => $i->accountingClass ? [
                    'id' => $i->accountingClass->id,
                    'code' => $i->accountingClass->code,
                    'name' => $i->accountingClass->name,
                ] : null,
                'management_class' => $i->managementClass ? [
                    'id' => $i->managementClass->id,
                    'code' => $i->managementClass->code,
                    'name' => $i->managementClass->name,
                ] : null,
                'cost_center' => $i->costCenter ? [
                    'id' => $i->costCenter->id,
                    'code' => $i->costCenter->code,
                    'name' => $i->costCenter->name,
                ] : null,
                'store' => $i->store ? [
                    'id' => $i->store->id,
                    'code' => $i->store->code,
                    'name' => $i->store->name,
                ] : null,
                'supplier' => $i->supplier,
                'justification' => $i->justification,
                'account_description' => $i->account_description,
                'class_description' => $i->class_description,
                'months' => $i->monthlyValues(),
                'year_total' => (float) $i->year_total,
            ])->values()->all() ?? [];

            $payload['status_history'] = $b->statusHistories?->map(fn ($h) => [
                'id' => $h->id,
                'event' => $h->event,
                'from_active' => $h->from_active,
                'to_active' => $h->to_active,
                'note' => $h->note,
                'changed_by' => $h->changedBy?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values()->all() ?? [];
        }

        return $payload;
    }
}
