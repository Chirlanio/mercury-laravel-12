<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Models\Employee;
use App\Models\Network;
use App\Models\Status;
use App\Models\Store;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of the stores.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $networkFilter = $request->get('network');
        $statusFilter = $request->get('status');

        $query = Store::query()
            ->with(['manager:id,name,short_name', 'supervisor:id,name,short_name', 'network'])
            ->withCount('employees');

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Apply network filter
        if ($networkFilter) {
            $query->where('network_id', $networkFilter);
        }

        // Apply status filter
        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status_id', $statusFilter);
        }

        // Apply sorting
        $allowedSortFields = ['name', 'code', 'network_id', 'store_order'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('store_order')->orderBy('name');
        }

        $stores = $query->paginate($perPage);

        // Get data for filters and modals
        $networks = Network::active()->orderBy('nome')->get(['id', 'nome as name']);
        $statuses = Status::orderBy('name')->get(['id', 'name']);
        $managers = Employee::where('status_id', 2)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);

        return Inertia::render('Stores/Index', [
            'stores' => $stores->through(function ($store) {
                return [
                    'id' => $store->id,
                    'code' => $store->code,
                    'name' => $store->name,
                    'display_name' => $store->display_name,
                    'cnpj' => $store->formatted_cnpj,
                    'company_name' => $store->company_name,
                    'address' => $store->address,
                    'network_id' => $store->network_id,
                    'network_name' => $store->network?->nome ?? $store->network_name,
                    'manager_id' => $store->manager_id,
                    'manager_name' => $store->manager?->short_name ?? $store->manager?->name ?? 'Não informado',
                    'supervisor_id' => $store->supervisor_id,
                    'supervisor_name' => $store->supervisor?->short_name ?? $store->supervisor?->name ?? 'Não informado',
                    'store_order' => $store->store_order,
                    'network_order' => $store->network_order,
                    'status_id' => $store->status_id,
                    'is_active' => $store->is_active,
                    'employees_count' => $store->employees_count,
                ];
            }),
            'networks' => $networks,
            'statuses' => $statuses,
            'managers' => $managers,
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'network' => $networkFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    /**
     * Store a newly created store in storage.
     */
    public function store(StoreStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Clean CNPJ
        $data['cnpj'] = preg_replace('/[^0-9]/', '', $data['cnpj']);

        // Apply uppercase to text fields
        $data['name'] = strtoupper($data['name']);
        $data['company_name'] = strtoupper($data['company_name']);
        $data['address'] = strtoupper($data['address']);
        $data['code'] = strtoupper($data['code']);

        // Set default values
        $data['status_id'] = $data['status_id'] ?? 1;

        $store = Store::create($data);

        $this->auditLogService->logModelCreated($store, auth()->user());

        return redirect()
            ->route('stores.index')
            ->with('success', 'Loja cadastrada com sucesso.');
    }

    /**
     * Display the specified store.
     */
    public function show(Request $request, Store $store): Response|\Illuminate\Http\JsonResponse
    {
        $store->load([
            'manager:id,name,short_name',
            'supervisor:id,name,short_name',
            'network',
            'employees' => function ($query) {
                $query->where('status_id', 2)
                    ->select(['id', 'name', 'short_name', 'profile_image', 'position_id', 'store_id'])
                    ->with('position:id,name')
                    ->orderBy('name')
                    ->limit(10);
            }
        ]);
        $store->loadCount('employees');

        $storeData = [
            'store' => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'display_name' => $store->display_name,
                'cnpj' => $store->cnpj,
                'formatted_cnpj' => $store->formatted_cnpj,
                'company_name' => $store->company_name,
                'state_registration' => $store->state_registration,
                'address' => $store->address,
                'network_id' => $store->network_id,
                'network_name' => $store->network?->nome ?? $store->network_name,
                'manager_id' => $store->manager_id,
                'manager' => $store->manager ? [
                    'id' => $store->manager->id,
                    'name' => $store->manager->name,
                    'short_name' => $store->manager->short_name,
                ] : null,
                'supervisor_id' => $store->supervisor_id,
                'supervisor' => $store->supervisor ? [
                    'id' => $store->supervisor->id,
                    'name' => $store->supervisor->name,
                    'short_name' => $store->supervisor->short_name,
                ] : null,
                'store_order' => $store->store_order,
                'network_order' => $store->network_order,
                'status_id' => $store->status_id,
                'is_active' => $store->is_active,
                'employees_count' => $store->employees_count,
                'employees' => $store->employees->map(fn ($emp) => [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'short_name' => $emp->short_name,
                    'position' => $emp->position?->name,
                    'avatar_url' => $emp->avatar_url ?? null,
                ]),
                'created_at' => $store->created_at?->format('d/m/Y H:i'),
                'updated_at' => $store->updated_at?->format('d/m/Y H:i'),
            ],
        ];

        // Return JSON for AJAX requests, Inertia page for regular navigation
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($storeData);
        }

        return Inertia::render('Stores/Show', $storeData);
    }

    /**
     * Show the form for editing the specified store.
     */
    public function edit(Store $store): Response
    {
        $networks = Network::active()->orderBy('nome')->get(['id', 'nome as name']);
        $statuses = Status::orderBy('name')->get(['id', 'name']);
        $managers = Employee::where('status_id', 2)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);

        return Inertia::render('Stores/Edit', [
            'store' => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'cnpj' => $store->cnpj,
                'company_name' => $store->company_name,
                'state_registration' => $store->state_registration,
                'address' => $store->address,
                'network_id' => $store->network_id,
                'manager_id' => $store->manager_id,
                'supervisor_id' => $store->supervisor_id,
                'store_order' => $store->store_order,
                'network_order' => $store->network_order,
                'status_id' => $store->status_id,
            ],
            'networks' => $networks,
            'statuses' => $statuses,
            'managers' => $managers,
        ]);
    }

    /**
     * Update the specified store in storage.
     */
    public function update(UpdateStoreRequest $request, Store $store): RedirectResponse
    {
        $data = $request->validated();

        // Store old values for audit
        $oldValues = $store->toArray();

        // Clean CNPJ if provided
        if (isset($data['cnpj'])) {
            $data['cnpj'] = preg_replace('/[^0-9]/', '', $data['cnpj']);
        }

        // Apply uppercase to text fields
        if (isset($data['name'])) {
            $data['name'] = strtoupper($data['name']);
        }
        if (isset($data['company_name'])) {
            $data['company_name'] = strtoupper($data['company_name']);
        }
        if (isset($data['address'])) {
            $data['address'] = strtoupper($data['address']);
        }
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $store->update($data);

        $this->auditLogService->logModelUpdated($store, $oldValues, auth()->user());

        return redirect()
            ->route('stores.index')
            ->with('success', 'Loja atualizada com sucesso.');
    }

    /**
     * Remove the specified store from storage.
     */
    public function destroy(Store $store): RedirectResponse
    {
        // Check if store has employees
        if ($store->employees()->exists()) {
            return redirect()
                ->route('stores.index')
                ->with('error', 'Não é possível excluir uma loja que possui funcionários vinculados.');
        }

        $this->auditLogService->logModelDeleted($store, auth()->user());

        $store->delete();

        return redirect()
            ->route('stores.index')
            ->with('success', 'Loja excluída com sucesso.');
    }

    /**
     * Activate a store.
     */
    public function activate(Store $store): RedirectResponse
    {
        $oldValues = $store->toArray();

        $store->update(['status_id' => 1]);

        $this->auditLogService->logModelUpdated($store, $oldValues, auth()->user());

        return redirect()
            ->back()
            ->with('success', 'Loja ativada com sucesso.');
    }

    /**
     * Deactivate a store.
     */
    public function deactivate(Store $store): RedirectResponse
    {
        $oldValues = $store->toArray();

        $store->update(['status_id' => 2]);

        $this->auditLogService->logModelUpdated($store, $oldValues, auth()->user());

        return redirect()
            ->back()
            ->with('success', 'Loja desativada com sucesso.');
    }

    /**
     * Get stores for select dropdown (API endpoint).
     */
    public function getForSelect(Request $request)
    {
        $networkId = $request->get('network_id');

        $query = Store::active()
            ->orderBy('store_order')
            ->orderBy('name');

        if ($networkId) {
            $query->where('network_id', $networkId);
        }

        $stores = $query->get(['id', 'code', 'name', 'network_id']);

        return response()->json([
            'stores' => $stores->map(fn ($store) => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'display_name' => $store->display_name,
                'network_id' => $store->network_id,
            ]),
        ]);
    }

    /**
     * Reorder stores within a network.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $request->validate([
            'stores' => 'required|array',
            'stores.*.id' => 'required|exists:stores,id',
            'stores.*.store_order' => 'required|integer|min:1',
        ]);

        foreach ($request->stores as $storeData) {
            Store::where('id', $storeData['id'])
                ->update(['store_order' => $storeData['store_order']]);
        }

        return redirect()
            ->back()
            ->with('success', 'Ordem das lojas atualizada com sucesso.');
    }
}
