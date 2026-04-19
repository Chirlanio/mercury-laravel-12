<?php

namespace App\Http\Controllers;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Http\Requests\AccountingClass\StoreAccountingClassRequest;
use App\Http\Requests\AccountingClass\UpdateAccountingClassRequest;
use App\Models\AccountingClass;
use App\Services\AccountingClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountingClassController extends Controller
{
    public function __construct(
        private AccountingClassService $service,
    ) {}

    public function index(Request $request): Response
    {
        $query = AccountingClass::query()
            ->with(['parent', 'createdBy', 'updatedBy'])
            ->notDeleted();

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('dre_group')) {
            $query->byDreGroup($request->string('dre_group')->toString());
        }

        if ($request->filled('nature')) {
            $query->where('nature', $request->string('nature')->toString());
        }

        if ($request->filled('accepts_entries')) {
            $query->where('accepts_entries', $request->boolean('accepts_entries'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('parent_id')) {
            if ($request->parent_id === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->integer('parent_id'));
            }
        }

        // Ordenação por code é naturalmente hierárquica (3.1 < 3.1.01 < 3.1.01.001)
        $query->orderBy('code');

        $accountingClasses = $query->paginate(25)
            ->withQueryString()
            ->through(fn ($c) => $this->format($c));

        return Inertia::render('AccountingClasses/Index', [
            'accountingClasses' => $accountingClasses,
            'filters' => $request->only(['search', 'dre_group', 'nature', 'accepts_entries', 'is_active', 'parent_id']),
            'statistics' => $this->buildStatistics(),
            'enums' => [
                'natures' => AccountingNature::options(),
                'dreGroups' => DreGroup::options(),
            ],
            'selects' => [
                // Apenas grupos sintéticos podem ser pais — folhas não
                'parents' => AccountingClass::query()
                    ->notDeleted()
                    ->syntheticGroups()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'parent_id']),
            ],
        ]);
    }

    public function statistics(): JsonResponse
    {
        return response()->json($this->buildStatistics());
    }

    public function tree(): JsonResponse
    {
        $all = AccountingClass::query()
            ->notDeleted()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id', 'nature', 'dre_group', 'accepts_entries', 'is_active']);

        return response()->json([
            'tree' => $this->buildTree($all),
        ]);
    }

    public function show(AccountingClass $accountingClass): JsonResponse
    {
        if ($accountingClass->isDeleted()) {
            return response()->json(['message' => 'Conta excluída.'], 404);
        }

        $accountingClass->load([
            'parent',
            'children' => fn ($q) => $q->whereNull('deleted_at')->orderBy('code'),
            'createdBy',
            'updatedBy',
        ]);

        return response()->json([
            'accountingClass' => $this->format($accountingClass, full: true),
        ]);
    }

    public function store(StoreAccountingClassRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('accounting-classes.index')
            ->with('success', 'Conta contábil cadastrada com sucesso.');
    }

    public function update(UpdateAccountingClassRequest $request, AccountingClass $accountingClass): RedirectResponse
    {
        try {
            $this->service->update($accountingClass, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('accounting-classes.index')
            ->with('success', 'Conta atualizada com sucesso.');
    }

    public function destroy(Request $request, AccountingClass $accountingClass): RedirectResponse
    {
        $reason = (string) $request->input('deleted_reason', '');

        try {
            $this->service->delete($accountingClass, $reason, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('accounting-classes.index')
            ->with('success', 'Conta excluída.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function buildStatistics(): array
    {
        $base = AccountingClass::query()->notDeleted();

        $byGroup = (clone $base)
            ->selectRaw('dre_group, COUNT(*) as count')
            ->groupBy('dre_group')
            ->pluck('count', 'dre_group')
            ->all();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'leaves' => (clone $base)->where('accepts_entries', true)->count(),
            'synthetic_groups' => (clone $base)->where('accepts_entries', false)->count(),
            'by_dre_group' => $byGroup,
        ];
    }

    protected function format(AccountingClass $c, bool $full = false): array
    {
        $natureValue = $c->nature instanceof AccountingNature ? $c->nature->value : $c->nature;
        $dreGroupValue = $c->dre_group instanceof DreGroup ? $c->dre_group->value : $c->dre_group;

        $payload = [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'description' => $c->description,
            'parent_id' => $c->parent_id,
            'parent_label' => $c->parent
                ? $c->parent->code.' · '.$c->parent->name
                : null,
            'nature' => $natureValue,
            'nature_label' => $c->nature?->label(),
            'nature_short' => $c->nature?->shortLabel(),
            'dre_group' => $dreGroupValue,
            'dre_group_label' => $c->dre_group?->label(),
            'dre_group_color' => $c->dre_group?->color(),
            'accepts_entries' => (bool) $c->accepts_entries,
            'sort_order' => (int) $c->sort_order,
            'is_active' => (bool) $c->is_active,
            'follows_natural_nature' => $c->followsNaturalNature(),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];

        if ($full) {
            $payload['created_by'] = $c->createdBy?->name;
            $payload['updated_by'] = $c->updatedBy?->name;
            $payload['children'] = $c->children?->map(fn ($child) => [
                'id' => $child->id,
                'code' => $child->code,
                'name' => $child->name,
                'accepts_entries' => (bool) $child->accepts_entries,
                'is_active' => (bool) $child->is_active,
            ])->values()->all() ?? [];
        }

        return $payload;
    }

    /**
     * Constrói árvore a partir de coleção flat ordenada por code.
     * Complexidade O(n) via mapa de id→node.
     */
    protected function buildTree($collection): array
    {
        $map = [];
        foreach ($collection as $item) {
            $node = [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'parent_id' => $item->parent_id,
                'nature' => $item->nature instanceof AccountingNature ? $item->nature->value : $item->nature,
                'dre_group' => $item->dre_group instanceof DreGroup ? $item->dre_group->value : $item->dre_group,
                'accepts_entries' => (bool) $item->accepts_entries,
                'is_active' => (bool) $item->is_active,
                'children' => [],
            ];
            $map[$item->id] = $node;
        }

        $roots = [];
        foreach ($map as $id => &$node) {
            if ($node['parent_id'] && isset($map[$node['parent_id']])) {
                $map[$node['parent_id']]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }

        return $roots;
    }
}
