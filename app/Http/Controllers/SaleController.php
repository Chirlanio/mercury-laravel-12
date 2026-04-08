<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Movement;
use App\Services\MovementSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $storeId = $request->get('store_id');
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $query = Sale::query()
            ->with(['store', 'employee'])
            ->forMonth((int) $month, (int) $year);

        // Filter by store for non-admin users
        $user = $request->user();
        if (!in_array($user->role, [Role::ADMIN, Role::SUPER_ADMIN])) {
            if ($user->employee && $user->employee->store_id) {
                $store = Store::where('code', $user->employee->store_id)->first();
                if ($store) {
                    $query->forStoreWithEcommerce($store->id);
                }
            }
        } elseif ($storeId) {
            $query->forStoreWithEcommerce((int) $storeId);
        }

        if ($search) {
            $query->whereHas('employee', function ($eq) use ($search) {
                $eq->where('name', 'like', "%{$search}%")
                   ->orWhere('short_name', 'like', "%{$search}%");
            });
        }

        $sales = $query->get();

        // Determine the effective store filter (explicit or from non-admin user)
        $effectiveStoreId = $storeId ? (int) $storeId : null;
        if (!$effectiveStoreId && !in_array($user->role, [Role::ADMIN, Role::SUPER_ADMIN])) {
            if ($user->employee && $user->employee->store_id) {
                $userStore = Store::where('code', $user->employee->store_id)->first();
                if ($userStore) {
                    $effectiveStoreId = $userStore->id;
                }
            }
        }

        if ($effectiveStoreId) {
            // With store filter: forStoreWithEcommerce already selected the right
            // sales (physical + e-commerce). Group all under the filtered store so
            // consultant totals include both physical and e-commerce.
            $grouped = $this->groupSalesUnderSingleStore($sales, $effectiveStoreId);
        } else {
            // No filter (all stores): remap e-commerce sales to the consultant's
            // physical store so totals include both.
            $grouped = $this->groupSalesWithEcommerceRemapping($sales);
        }

        $grandTotals = [
            'total_sales' => round((float) $sales->sum('total_sales'), 2),
            'qtde_total' => (int) $sales->sum('qtde_total'),
            'total_stores' => count($grouped),
            'total_employees' => $sales->unique('employee_id')->count(),
        ];

        $stores = Store::active()
            ->orderedByNetwork()
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->display_name]);

        // Check if movements data exists (source of truth)
        $lastMovementDate = Movement::max('movement_date');
        $lastSalesRefresh = Sale::fromCigam()->max('updated_at');

        return Inertia::render('Sales/Index', [
            'salesByStore' => $grouped,
            'grandTotals' => $grandTotals,
            'stores' => $stores,
            'filters' => [
                'store_id' => $storeId,
                'month' => (int) $month,
                'year' => (int) $year,
                'search' => $search,
            ],
            'lastMovementDate' => $lastMovementDate ? Carbon::parse($lastMovementDate)->format('d/m/Y') : null,
            'lastSalesRefresh' => $lastSalesRefresh,
        ]);
    }

    /**
     * Group all sales under a single store (used when a store filter is active).
     */
    protected function groupSalesUnderSingleStore($sales, int $storeId): array
    {
        if ($sales->isEmpty()) {
            return [];
        }

        $store = Store::find($storeId);
        $employees = $sales->groupBy('employee_id')->map(function ($empSales, $employeeId) {
            $employee = $empSales->first()->employee;

            return [
                'employee_id' => (int) $employeeId,
                'employee_name' => $employee ? $employee->short_name : 'N/A',
                'total_sales' => round((float) $empSales->sum('total_sales'), 2),
                'qtde_total' => (int) $empSales->sum('qtde_total'),
            ];
        })->values()->sortByDesc('total_sales')->values()->all();

        return [[
            'store_id' => $storeId,
            'store_name' => $store ? $store->display_name : 'N/A',
            'total_sales' => round((float) $sales->sum('total_sales'), 2),
            'qtde_total' => (int) $sales->sum('qtde_total'),
            'employees' => $employees,
        ]];
    }

    /**
     * Group sales by store, remapping e-commerce sales to the consultant's
     * physical store based on active employment contracts.
     */
    protected function groupSalesWithEcommerceRemapping($sales): array
    {
        $ecommerceStore = Store::where('code', Store::ECOMMERCE_CODE)->first();
        $ecommerceStoreId = $ecommerceStore ? $ecommerceStore->id : null;

        // Build employee → home physical store mapping
        $employeeHomeStoreId = [];
        if ($ecommerceStoreId) {
            $employeeIds = $sales->pluck('employee_id')->unique()->values()->toArray();
            if (!empty($employeeIds)) {
                $contracts = \App\Models\EmploymentContract::active()
                    ->whereIn('employee_id', $employeeIds)
                    ->get();

                $storeCodes = $contracts->pluck('store_id')->unique()->values()->toArray();
                $codeToId = Store::whereIn('code', $storeCodes)->pluck('id', 'code');

                foreach ($contracts as $contract) {
                    $mappedId = $codeToId[$contract->store_id] ?? null;
                    if ($mappedId && $mappedId !== $ecommerceStoreId) {
                        $employeeHomeStoreId[$contract->employee_id] = $mappedId;
                    }
                }
            }
        }

        // Assign display store: e-commerce sales by contracted employees → physical store
        $salesWithDisplay = $sales->map(function ($sale) use ($ecommerceStoreId, $employeeHomeStoreId) {
            if ($sale->store_id === $ecommerceStoreId && isset($employeeHomeStoreId[$sale->employee_id])) {
                $sale->display_store_id = $employeeHomeStoreId[$sale->employee_id];
            } else {
                $sale->display_store_id = $sale->store_id;
            }

            return $sale;
        });

        $storesById = Store::whereIn('id', $salesWithDisplay->pluck('display_store_id')->unique())->get()->keyBy('id');

        return $salesWithDisplay->groupBy('display_store_id')->map(function ($storeSales, $displayStoreId) use ($storesById) {
            $store = $storesById[(int) $displayStoreId] ?? null;
            $employees = $storeSales->groupBy('employee_id')->map(function ($empSales, $employeeId) {
                $employee = $empSales->first()->employee;

                return [
                    'employee_id' => (int) $employeeId,
                    'employee_name' => $employee ? $employee->short_name : 'N/A',
                    'total_sales' => round((float) $empSales->sum('total_sales'), 2),
                    'qtde_total' => (int) $empSales->sum('qtde_total'),
                ];
            })->values()->sortByDesc('total_sales')->values()->all();

            return [
                'store_id' => (int) $displayStoreId,
                'store_name' => $store ? $store->display_name : 'N/A',
                'total_sales' => round((float) $storeSales->sum('total_sales'), 2),
                'qtde_total' => (int) $storeSales->sum('qtde_total'),
                'employees' => $employees,
            ];
        })->values()->sortByDesc('total_sales')->values()->all();
    }

    public function employeeDailySales(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'store_id' => 'required|exists:stores,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2099',
        ]);

        $employeeId = (int) $request->employee_id;
        $storeId = (int) $request->store_id;
        $month = (int) $request->month;
        $year = (int) $request->year;

        $employee = \App\Models\Employee::find($employeeId);
        $store = Store::find($storeId);
        $ecommerceStore = Store::where('code', Store::ECOMMERCE_CODE)->first();

        // Get ALL sales for this employee in the month (across all stores)
        $sales = Sale::with('store')
            ->forEmployee($employeeId)
            ->forMonth($month, $year)
            ->orderBy('date_sales')
            ->get();

        $ecommerceStoreId = $ecommerceStore ? $ecommerceStore->id : null;

        $dailySales = $sales->map(function ($sale) use ($ecommerceStoreId) {
            $isEcommerce = $sale->store_id === $ecommerceStoreId;

            return [
                'id' => $sale->id,
                'date_sales' => $sale->date_sales->format('d/m/Y'),
                'date_sales_raw' => $sale->date_sales->format('Y-m-d'),
                'total_sales' => (float) $sale->total_sales,
                'qtde_total' => (int) $sale->qtde_total,
                'source' => $sale->source,
                'store_id' => $sale->store_id,
                'store_name' => $sale->store ? $sale->store->display_name : 'N/A',
                'is_ecommerce' => $isEcommerce,
            ];
        })->all();

        $storeSales = $sales->filter(fn ($s) => $s->store_id !== $ecommerceStoreId);
        $ecommerceSales = $sales->filter(fn ($s) => $s->store_id === $ecommerceStoreId);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'short_name' => $employee->short_name,
            ],
            'store' => [
                'id' => $store->id,
                'name' => $store->display_name,
            ],
            'daily_sales' => array_values($dailySales),
            'totals' => [
                'store_total' => round((float) $storeSales->sum('total_sales'), 2),
                'store_qtde' => (int) $storeSales->sum('qtde_total'),
                'ecommerce_total' => round((float) $ecommerceSales->sum('total_sales'), 2),
                'ecommerce_qtde' => (int) $ecommerceSales->sum('qtde_total'),
                'total' => round((float) $sales->sum('total_sales'), 2),
                'total_qtde' => (int) $sales->sum('qtde_total'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'employee_id' => 'required|exists:employees,id',
            'date_sales' => 'required|date|before_or_equal:today',
            'total_sales' => 'required|numeric|min:0.01',
            'qtde_total' => 'required|integer|min:1',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!$validator->errors()->any()) {
                $exists = Sale::where('store_id', $request->store_id)
                    ->where('employee_id', $request->employee_id)
                    ->where('date_sales', $request->date_sales)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('date_sales', 'Já existe um registro de venda para este funcionário, loja e data.');
                }
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Sale::create([
            'store_id' => $request->store_id,
            'employee_id' => $request->employee_id,
            'date_sales' => $request->date_sales,
            'total_sales' => $request->total_sales,
            'qtde_total' => $request->qtde_total,
            'source' => 'manual',
            'created_by_user_id' => auth()->id(),
            'updated_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('sales.index')
            ->with('success', 'Venda registrada com sucesso.');
    }

    public function show(Sale $sale)
    {
        $sale->load(['store', 'employee', 'createdBy', 'updatedBy']);

        return response()->json([
            'sale' => [
                'id' => $sale->id,
                'date_sales' => $sale->date_sales->format('d/m/Y'),
                'date_sales_raw' => $sale->date_sales->format('Y-m-d'),
                'store_id' => $sale->store_id,
                'store_name' => $sale->store ? $sale->store->display_name : 'N/A',
                'employee_id' => $sale->employee_id,
                'employee_name' => $sale->employee ? $sale->employee->name : 'N/A',
                'total_sales' => (float) $sale->total_sales,
                'formatted_total' => $sale->formatted_total,
                'qtde_total' => $sale->qtde_total,
                'source' => $sale->source,
                'created_by' => $sale->createdBy ? $sale->createdBy->name : null,
                'created_at' => $sale->created_at->format('d/m/Y H:i'),
                'updated_by' => $sale->updatedBy ? $sale->updatedBy->name : null,
                'updated_at' => $sale->updated_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function edit(Sale $sale)
    {
        return response()->json([
            'sale' => [
                'id' => $sale->id,
                'store_id' => $sale->store_id,
                'employee_id' => $sale->employee_id,
                'date_sales' => $sale->date_sales->format('Y-m-d'),
                'total_sales' => (float) $sale->total_sales,
                'qtde_total' => $sale->qtde_total,
                'source' => $sale->source,
            ],
        ]);
    }

    public function update(Request $request, Sale $sale)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'employee_id' => 'required|exists:employees,id',
            'date_sales' => 'required|date|before_or_equal:today',
            'total_sales' => 'required|numeric|min:0.01',
            'qtde_total' => 'required|integer|min:1',
        ]);

        $validator->after(function ($validator) use ($request, $sale) {
            if (!$validator->errors()->any()) {
                $exists = Sale::where('store_id', $request->store_id)
                    ->where('employee_id', $request->employee_id)
                    ->where('date_sales', $request->date_sales)
                    ->where('id', '!=', $sale->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('date_sales', 'Já existe um registro de venda para este funcionário, loja e data.');
                }
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $sale->update([
            'store_id' => $request->store_id,
            'employee_id' => $request->employee_id,
            'date_sales' => $request->date_sales,
            'total_sales' => $request->total_sales,
            'qtde_total' => $request->qtde_total,
            'updated_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('sales.index')
            ->with('success', 'Venda atualizada com sucesso.');
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();

        return redirect()->route('sales.index')
            ->with('success', 'Venda excluída com sucesso.');
    }

    public function statistics(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $storeId = $request->get('store_id');

        $baseQuery = Sale::query();

        $user = $request->user();
        if (!in_array($user->role, [Role::ADMIN, Role::SUPER_ADMIN])) {
            if ($user->employee && $user->employee->store_id) {
                $store = Store::where('code', $user->employee->store_id)->first();
                if ($store) {
                    $baseQuery->forStoreWithEcommerce($store->id);
                }
            }
        } elseif ($storeId) {
            $baseQuery->forStoreWithEcommerce((int) $storeId);
        }

        // Current month
        $currentMonthTotal = (clone $baseQuery)->forMonth((int) $month, (int) $year)->sum('total_sales');

        // Previous month
        $prevDate = Carbon::create($year, $month, 1)->subMonth();
        $lastMonthTotal = (clone $baseQuery)->forMonth($prevDate->month, $prevDate->year)->sum('total_sales');

        // Same month last year
        $sameMonthLastYear = (clone $baseQuery)->forMonth((int) $month, (int) $year - 1)->sum('total_sales');

        // Variations
        $variation = $lastMonthTotal > 0
            ? round((($currentMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100, 2)
            : 0;

        $yoyVariation = $sameMonthLastYear > 0
            ? round((($currentMonthTotal - $sameMonthLastYear) / $sameMonthLastYear) * 100, 2)
            : 0;

        // Active stores and consultants in current month
        $currentMonthQuery = (clone $baseQuery)->forMonth((int) $month, (int) $year);
        $activeStores = (clone $currentMonthQuery)->distinct('store_id')->count('store_id');
        $activeConsultants = (clone $currentMonthQuery)->distinct('employee_id')->count('employee_id');
        $totalRecords = (clone $currentMonthQuery)->count();

        $avgPerStore = $activeStores > 0 ? round($currentMonthTotal / $activeStores, 2) : 0;
        $avgPerConsultant = $activeConsultants > 0 ? round($currentMonthTotal / $activeConsultants, 2) : 0;

        // Last sync date
        $lastSync = Sale::fromCigam()->max('updated_at');

        return response()->json([
            'current_month_total' => round((float) $currentMonthTotal, 2),
            'last_month_total' => round((float) $lastMonthTotal, 2),
            'variation' => $variation,
            'same_month_last_year' => round((float) $sameMonthLastYear, 2),
            'yoy_variation' => $yoyVariation,
            'active_stores' => $activeStores,
            'active_consultants' => $activeConsultants,
            'total_records' => $totalRecords,
            'avg_per_store' => $avgPerStore,
            'avg_per_consultant' => $avgPerConsultant,
            'last_sync' => $lastSync,
        ]);
    }

    /**
     * Refresh sales summary from movements table (source of truth).
     * Replaces the old direct CIGAM sync — now movements are synced first,
     * then sales are aggregated from movements.
     */
    public function refreshFromMovements(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2099',
        ]);

        set_time_limit(0);

        $start = Carbon::create($request->year, $request->month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        // Check if movements exist for this period
        $movementCount = Movement::forDateRange($start->toDateString(), $end->toDateString())->count();

        if ($movementCount === 0) {
            return back()->with('warning', 'Nenhuma movimentação encontrada para este período. Sincronize as movimentações primeiro.');
        }

        $service = app(MovementSyncService::class);
        $result = $service->refreshSalesSummary($start, $end);

        $msg = "Vendas atualizadas: {$result['inserted']} inseridas, {$result['updated']} atualizadas.";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} ignoradas (lojas não cadastradas).";
        }

        return back()->with('success', $msg);
    }

    public function bulkDeletePreview(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:month,range',
            'month' => 'required_if:mode,month|nullable|integer|between:1,12',
            'year' => 'required_if:mode,month|nullable|integer|between:2020,2099',
            'start_date' => 'required_if:mode,range|nullable|date',
            'end_date' => 'required_if:mode,range|nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $query = Sale::query();

        if ($request->mode === 'month') {
            $query->forMonth((int) $request->month, (int) $request->year);
        } else {
            $query->forDateRange($request->start_date, $request->end_date);
        }

        if ($request->store_id) {
            $query->forStore((int) $request->store_id);
        }

        $totalRecords = $query->count();
        $totalValue = $query->sum('total_sales');
        $affectedStores = (clone $query)->distinct('store_id')->count('store_id');
        $affectedEmployees = (clone $query)->distinct('employee_id')->count('employee_id');

        return response()->json([
            'total_records' => $totalRecords,
            'total_value' => round((float) $totalValue, 2),
            'affected_stores' => $affectedStores,
            'affected_employees' => $affectedEmployees,
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:month,range',
            'month' => 'required_if:mode,month|nullable|integer|between:1,12',
            'year' => 'required_if:mode,month|nullable|integer|between:2020,2099',
            'start_date' => 'required_if:mode,range|nullable|date',
            'end_date' => 'required_if:mode,range|nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $query = Sale::query();

        if ($request->mode === 'month') {
            $query->forMonth((int) $request->month, (int) $request->year);
        } else {
            $query->forDateRange($request->start_date, $request->end_date);
        }

        if ($request->store_id) {
            $query->forStore((int) $request->store_id);
        }

        $deleted = $query->delete();

        return redirect()->route('sales.index')
            ->with('success', "{$deleted} registros de vendas excluídos com sucesso.");
    }
}
