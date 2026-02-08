<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Services\CigamSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'date_sales');
        $sortDirection = $request->get('direction', 'desc');
        $storeId = $request->get('store_id');
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $query = Sale::query()
            ->with(['store', 'employee'])
            ->forMonth((int) $month, (int) $year);

        // Filter by store for non-admin users
        $user = $request->user();
        if (!in_array($user->role, [Role::ADMIN->value, Role::SUPER_ADMIN->value])) {
            if ($user->employee && $user->employee->store_id) {
                $store = Store::where('code', $user->employee->store_id)->first();
                if ($store) {
                    $query->forStore($store->id);
                }
            }
        } elseif ($storeId) {
            $query->forStore((int) $storeId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($eq) use ($search) {
                    $eq->where('name', 'like', "%{$search}%")
                       ->orWhere('short_name', 'like', "%{$search}%");
                })->orWhereHas('store', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        $allowedSortFields = ['date_sales', 'total_sales', 'qtde_total', 'source'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('date_sales', 'desc');
        }

        $sales = $query->paginate($perPage)->through(function ($sale) {
            return [
                'id' => $sale->id,
                'date_sales' => $sale->date_sales->format('d/m/Y'),
                'date_sales_raw' => $sale->date_sales->format('Y-m-d'),
                'store_name' => $sale->store ? $sale->store->display_name : 'N/A',
                'store_id' => $sale->store_id,
                'employee_name' => $sale->employee ? $sale->employee->short_name : 'N/A',
                'employee_id' => $sale->employee_id,
                'total_sales' => (float) $sale->total_sales,
                'formatted_total' => $sale->formatted_total,
                'qtde_total' => $sale->qtde_total,
                'source' => $sale->source,
            ];
        });

        $stores = Store::active()
            ->orderedByNetwork()
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->display_name]);

        $cigamAvailable = (new CigamSyncService())->isAvailable();

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'stores' => $stores,
            'filters' => [
                'store_id' => $storeId,
                'month' => (int) $month,
                'year' => (int) $year,
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'cigamAvailable' => $cigamAvailable,
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
        if (!in_array($user->role, [Role::ADMIN->value, Role::SUPER_ADMIN->value])) {
            if ($user->employee && $user->employee->store_id) {
                $store = Store::where('code', $user->employee->store_id)->first();
                if ($store) {
                    $baseQuery->forStore($store->id);
                }
            }
        } elseif ($storeId) {
            $baseQuery->forStore((int) $storeId);
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

    public function syncAuto()
    {
        $service = new CigamSyncService();

        if (!$service->isAvailable()) {
            return back()->with('error', 'Conexão CIGAM não disponível. Verifique as configurações.');
        }

        $lastDate = Sale::fromCigam()->max('date_sales');
        $start = $lastDate ? Carbon::parse($lastDate)->addDay() : Carbon::now()->subMonth()->startOfMonth();
        $end = Carbon::yesterday();

        if ($start->gt($end)) {
            return back()->with('info', 'Dados já estão atualizados até ontem.');
        }

        $result = $service->syncDateRange($start, $end);

        return back()->with('success', "Sincronização concluída: {$result['inserted']} inseridos, {$result['updated']} atualizados, {$result['errors']} erros.");
    }

    public function syncByMonth(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2099',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $service = new CigamSyncService();

        if (!$service->isAvailable()) {
            return back()->with('error', 'Conexão CIGAM não disponível.');
        }

        $start = Carbon::create($request->year, $request->month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        if ($end->isFuture()) {
            $end = Carbon::yesterday();
        }

        $result = $service->syncDateRange($start, $end, $request->store_id);

        return back()->with('success', "Sincronização concluída: {$result['inserted']} inseridos, {$result['updated']} atualizados, {$result['errors']} erros.");
    }

    public function syncByDateRange(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $service = new CigamSyncService();

        if (!$service->isAvailable()) {
            return back()->with('error', 'Conexão CIGAM não disponível.');
        }

        $result = $service->syncDateRange(
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date),
            $request->store_id
        );

        return back()->with('success', "Sincronização concluída: {$result['inserted']} inseridos, {$result['updated']} atualizados, {$result['errors']} erros.");
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
