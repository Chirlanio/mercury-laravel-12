<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Imports\StoreGoalsImport;
use App\Models\ConfirmedSale;
use App\Models\ConsultantGoal;
use App\Models\EmploymentContract;
use App\Models\PercentageAward;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreGoal;
use App\Services\GoalRedistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class StoreGoalController extends Controller
{
    public function index(Request $request)
    {
        $storeId = $request->get('store_id');
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $query = StoreGoal::query()
            ->with(['store', 'createdBy'])
            ->withCount('consultantGoals')
            ->forMonth((int) $month, (int) $year);

        // Filter by store for non-admin users
        $user = $request->user();
        if (!in_array($user->role, [Role::ADMIN, Role::SUPER_ADMIN])) {
            if ($user->employee && $user->employee->store_id) {
                $store = Store::where('code', $user->employee->store_id)->first();
                if ($store) {
                    $query->forStore($store->id);
                }
            }
        } elseif ($storeId) {
            $query->forStore((int) $storeId);
        }

        $goals = $query->orderBy('store_id')->get();

        // Check confirmation status per store
        $confirmedCounts = ConfirmedSale::whereIn('store_id', $goals->pluck('store_id'))
            ->where('reference_month', (int) $month)
            ->where('reference_year', (int) $year)
            ->selectRaw('store_id, COUNT(*) as count')
            ->groupBy('store_id')
            ->pluck('count', 'store_id');

        $goals = $goals->map(fn ($goal) => [
            'id' => $goal->id,
            'store_id' => $goal->store_id,
            'store_name' => $goal->store->display_name ?? $goal->store->name,
            'reference_month' => $goal->reference_month,
            'reference_year' => $goal->reference_year,
            'period_label' => $goal->period_label,
            'goal_amount' => (float) $goal->goal_amount,
            'super_goal' => (float) $goal->super_goal,
            'business_days' => $goal->business_days,
            'non_working_days' => $goal->non_working_days,
            'consultant_goals_count' => $goal->consultant_goals_count,
            'has_confirmed_sales' => ($confirmedCounts->get($goal->store_id, 0) > 0),
            'created_by' => $goal->createdBy?->name,
            'created_at' => $goal->created_at?->format('d/m/Y H:i'),
        ]);

        $stores = Store::active()
            ->orderedByNetwork()
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->display_name]);

        return Inertia::render('StoreGoals/Index', [
            'goals' => $goals,
            'stores' => $stores,
            'filters' => [
                'store_id' => $storeId,
                'month' => (int) $month,
                'year' => (int) $year,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'reference_month' => 'required|integer|min:1|max:12',
            'reference_year' => 'required|integer|min:2020|max:2099',
            'goal_amount' => 'required|numeric|min:0.01',
            'business_days' => 'required|integer|min:1|max:31',
            'non_working_days' => 'nullable|integer|min:0|max:15',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Check unique constraint
        $exists = StoreGoal::where('store_id', $request->store_id)
            ->where('reference_month', $request->reference_month)
            ->where('reference_year', $request->reference_year)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'store_id' => 'Já existe uma meta para esta loja neste período.',
            ])->withInput();
        }

        $goalAmount = (float) $request->goal_amount;

        $storeGoal = StoreGoal::create([
            'store_id' => $request->store_id,
            'reference_month' => $request->reference_month,
            'reference_year' => $request->reference_year,
            'goal_amount' => $goalAmount,
            'super_goal' => StoreGoal::calculateSuperGoal($goalAmount),
            'business_days' => $request->business_days,
            'non_working_days' => $request->non_working_days ?? 0,
            'created_by_user_id' => auth()->id(),
        ]);

        // Auto-redistribute to consultants
        $result = (new GoalRedistributionService())->redistribute($storeGoal);

        return redirect()->route('store-goals.index', [
            'month' => $request->reference_month,
            'year' => $request->reference_year,
        ])->with('success', "Meta criada com sucesso! {$result['message']}");
    }

    public function show(StoreGoal $storeGoal)
    {
        $storeGoal->load(['store.manager', 'consultantGoals.employee', 'createdBy', 'updatedBy']);

        $awards = PercentageAward::all()->keyBy('level');

        // Get system sales data
        $salesByEmployee = Sale::forMonth($storeGoal->reference_month, $storeGoal->reference_year)
            ->forStoreWithEcommerce($storeGoal->store_id)
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($sales) => round((float) $sales->sum('total_sales'), 2));

        // Get confirmed sales
        $confirmedByEmployee = ConfirmedSale::forStore($storeGoal->store_id)
            ->forMonth($storeGoal->reference_month, $storeGoal->reference_year)
            ->pluck('sale_value', 'employee_id');

        $hasConfirmedSales = $confirmedByEmployee->isNotEmpty();
        $totalSales = 0;
        $totalAwards = 0;

        $consultants = $storeGoal->consultantGoals->map(function ($cg) use ($salesByEmployee, $confirmedByEmployee, $awards, &$totalSales, &$totalAwards) {
            $systemSales = $salesByEmployee->get($cg->employee_id, 0);
            $confirmedValue = $confirmedByEmployee->get($cg->employee_id);
            $effectiveSales = $confirmedValue !== null ? round((float) $confirmedValue, 2) : $systemSales;
            $totalSales += $effectiveSales;

            $achievementPct = $cg->individual_goal > 0
                ? round(($effectiveSales / $cg->individual_goal) * 100, 1)
                : 0;

            $tier = $cg->getAchievementTier($effectiveSales);
            $award = $awards->get($cg->level_snapshot);
            $awardPct = $award ? $award->getPercentageForTier($tier) : 0;
            $awardAmount = round($effectiveSales * ($awardPct / 100), 2);
            $totalAwards += $awardAmount;

            return [
                'id' => $cg->id,
                'employee_id' => $cg->employee_id,
                'employee_name' => $cg->employee?->name ?? 'N/A',
                'level_snapshot' => $cg->level_snapshot,
                'weight' => (float) $cg->weight,
                'working_days' => $cg->working_days,
                'deducted_days' => $cg->deducted_days,
                'individual_goal' => (float) $cg->individual_goal,
                'super_goal' => (float) $cg->super_goal,
                'hiper_goal' => (float) $cg->hiper_goal,
                'system_sales' => $systemSales,
                'confirmed_sales' => $confirmedValue !== null ? round((float) $confirmedValue, 2) : null,
                'actual_sales' => $effectiveSales,
                'achievement_pct' => $achievementPct,
                'tier' => $tier,
                'award_pct' => $awardPct,
                'award_amount' => $awardAmount,
            ];
        })->sortByDesc('actual_sales')->values();

        $goalAmount = (float) $storeGoal->goal_amount;
        $superGoal = (float) $storeGoal->super_goal;
        $hiperGoal = round($superGoal * StoreGoal::SUPER_MULTIPLIER, 2);

        $goalAchievementPct = $goalAmount > 0
            ? round(($totalSales / $goalAmount) * 100, 1)
            : 0;

        $missingForGoal = max(0, $goalAmount - $totalSales);

        // Percentage awards config
        $awardsConfig = $awards->map(fn ($a) => [
            'level' => $a->level,
            'no_goal_pct' => (float) $a->no_goal_pct,
            'goal_pct' => (float) $a->goal_pct,
            'super_goal_pct' => (float) $a->super_goal_pct,
            'hiper_goal_pct' => (float) $a->hiper_goal_pct,
        ])->values();

        // Days in month
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $storeGoal->reference_month, $storeGoal->reference_year);

        return response()->json([
            'id' => $storeGoal->id,
            'store_name' => $storeGoal->store->display_name ?? $storeGoal->store->name,
            'store_id' => $storeGoal->store_id,
            'manager_name' => $storeGoal->store->manager?->name ?? null,
            'reference_month' => $storeGoal->reference_month,
            'reference_year' => $storeGoal->reference_year,
            'period_label' => $storeGoal->period_label,
            'goal_amount' => $goalAmount,
            'super_goal' => $superGoal,
            'hiper_goal' => $hiperGoal,
            'business_days' => $storeGoal->business_days,
            'non_working_days' => $storeGoal->non_working_days,
            'days_in_month' => $daysInMonth,
            'total_sales' => round($totalSales, 2),
            'total_awards' => round($totalAwards, 2),
            'missing_for_goal' => round($missingForGoal, 2),
            'achievement_pct' => $goalAchievementPct,
            'has_confirmed_sales' => $hasConfirmedSales,
            'consultants' => $consultants,
            'awards_config' => $awardsConfig,
            'created_by' => $storeGoal->createdBy?->name,
            'updated_by' => $storeGoal->updatedBy?->name,
            'created_at' => $storeGoal->created_at?->format('d/m/Y H:i'),
            'updated_at' => $storeGoal->updated_at?->format('d/m/Y H:i'),
        ]);
    }

    public function update(Request $request, StoreGoal $storeGoal)
    {
        $validator = Validator::make($request->all(), [
            'goal_amount' => 'required|numeric|min:0.01',
            'business_days' => 'required|integer|min:1|max:31',
            'non_working_days' => 'nullable|integer|min:0|max:15',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $goalAmount = (float) $request->goal_amount;

        $storeGoal->update([
            'goal_amount' => $goalAmount,
            'super_goal' => StoreGoal::calculateSuperGoal($goalAmount),
            'business_days' => $request->business_days,
            'non_working_days' => $request->non_working_days ?? 0,
            'updated_by_user_id' => auth()->id(),
        ]);

        // Re-redistribute after update
        $result = (new GoalRedistributionService())->redistribute($storeGoal);

        return redirect()->route('store-goals.index', [
            'month' => $storeGoal->reference_month,
            'year' => $storeGoal->reference_year,
        ])->with('success', "Meta atualizada! {$result['message']}");
    }

    public function destroy(StoreGoal $storeGoal)
    {
        $month = $storeGoal->reference_month;
        $year = $storeGoal->reference_year;

        $storeGoal->delete();

        return redirect()->route('store-goals.index', [
            'month' => $month,
            'year' => $year,
        ])->with('success', 'Meta excluída com sucesso!');
    }

    public function redistribute(StoreGoal $storeGoal)
    {
        $result = (new GoalRedistributionService())->redistribute($storeGoal);

        return redirect()->route('store-goals.index', [
            'month' => $storeGoal->reference_month,
            'year' => $storeGoal->reference_year,
        ])->with('success', "Redistribuição concluída! {$result['message']}");
    }

    public function statistics(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $goals = StoreGoal::forMonth($month, $year)->with('store')->get();
        $totalGoalAmount = $goals->sum('goal_amount');
        $totalSuperGoal = $goals->sum('super_goal');

        // Previous month for variation
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear = $month === 1 ? $year - 1 : $year;
        $prevGoals = StoreGoal::forMonth($prevMonth, $prevYear)->get();
        $prevTotalGoal = $prevGoals->sum('goal_amount');

        $goalVariation = $prevTotalGoal > 0
            ? round((($totalGoalAmount - $prevTotalGoal) / $prevTotalGoal) * 100, 1)
            : null;

        // Sales and achievement per store
        // Use raw total to avoid double-counting e-commerce sales
        // (forStoreWithEcommerce attributes Z441 sales to physical stores,
        //  so summing across stores would count them twice)
        $totalSales = (float) Sale::forMonth($month, $year)->sum('total_sales');

        $storesAboveGoal = 0;
        $storesAboveSuper = 0;
        $storeRanking = [];

        // Collect employee IDs attributed to physical stores (to exclude from Z441 count)
        $ecommerceStore = Store::where('code', Store::ECOMMERCE_CODE)->first();
        $attributedEmployeeIds = [];
        if ($ecommerceStore) {
            foreach ($goals as $goal) {
                if ($goal->store_id === $ecommerceStore->id) {
                    continue;
                }
                $store = $goal->store;
                if ($store) {
                    $empIds = EmploymentContract::active()
                        ->where('store_id', $store->code)
                        ->pluck('employee_id')
                        ->unique()
                        ->toArray();
                    $attributedEmployeeIds = array_merge($attributedEmployeeIds, $empIds);
                }
            }
            $attributedEmployeeIds = array_unique($attributedEmployeeIds);
        }

        foreach ($goals as $goal) {
            // For e-commerce store: only count sales NOT attributed to physical stores
            if ($ecommerceStore && $goal->store_id === $ecommerceStore->id) {
                $query = Sale::forMonth($month, $year)->where('store_id', $ecommerceStore->id);
                if (! empty($attributedEmployeeIds)) {
                    $query->whereNotIn('employee_id', $attributedEmployeeIds);
                }
                $storeSales = (float) $query->sum('total_sales');
            } else {
                $storeSales = (float) Sale::forMonth($month, $year)
                    ->forStoreWithEcommerce($goal->store_id)
                    ->sum('total_sales');
            }

            $pct = $goal->goal_amount > 0 ? round(($storeSales / $goal->goal_amount) * 100, 1) : 0;

            if ($storeSales >= $goal->goal_amount) $storesAboveGoal++;
            if ($storeSales >= $goal->super_goal) $storesAboveSuper++;

            $storeRanking[] = [
                'store_id' => $goal->store_id,
                'store_name' => $goal->store->display_name ?? $goal->store->name,
                'goal_amount' => (float) $goal->goal_amount,
                'super_goal' => (float) $goal->super_goal,
                'sales' => round($storeSales, 2),
                'achievement_pct' => $pct,
            ];
        }

        usort($storeRanking, fn ($a, $b) => $b['achievement_pct'] <=> $a['achievement_pct']);

        $activeStoresCount = Store::active()->count();
        $storesWithGoals = $goals->count();
        $coveragePct = $activeStoresCount > 0
            ? round(($storesWithGoals / $activeStoresCount) * 100, 1) : 0;

        $achievementPct = $totalGoalAmount > 0
            ? round(($totalSales / $totalGoalAmount) * 100, 1) : 0;

        return response()->json([
            'total_goal_amount' => round((float) $totalGoalAmount, 2),
            'total_super_goal' => round((float) $totalSuperGoal, 2),
            'total_sales' => round($totalSales, 2),
            'achievement_pct' => $achievementPct,
            'goal_variation' => $goalVariation,
            'stores_with_goals' => $storesWithGoals,
            'stores_above_goal' => $storesAboveGoal,
            'stores_above_super' => $storesAboveSuper,
            'active_stores' => $activeStoresCount,
            'coverage_pct' => $coveragePct,
            'store_ranking' => $storeRanking,
        ]);
    }

    public function achievementByConsultant(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);
        $storeId = $request->get('store_id');

        $query = ConsultantGoal::with(['employee', 'storeGoal.store'])
            ->forMonth($month, $year);

        if ($storeId) {
            $query->whereHas('storeGoal', fn ($q) => $q->where('store_id', $storeId));
        }

        $consultantGoals = $query->get();
        $awards = PercentageAward::all()->keyBy('level');

        $ranking = $consultantGoals->map(function ($cg) use ($month, $year, $awards) {
            $sales = (float) Sale::forMonth($month, $year)
                ->forEmployee($cg->employee_id)
                ->sum('total_sales');

            $pct = $cg->individual_goal > 0
                ? round(($sales / $cg->individual_goal) * 100, 1) : 0;

            $tier = $cg->getAchievementTier($sales);
            $award = $awards->get($cg->level_snapshot);
            $awardPct = $award ? $award->getPercentageForTier($tier) : 0;
            $awardAmount = round($sales * ($awardPct / 100), 2);

            return [
                'employee_id' => $cg->employee_id,
                'employee_name' => $cg->employee?->name ?? 'N/A',
                'store_name' => $cg->storeGoal?->store?->display_name ?? 'N/A',
                'level' => $cg->level_snapshot,
                'individual_goal' => (float) $cg->individual_goal,
                'super_goal' => (float) $cg->super_goal,
                'hiper_goal' => (float) $cg->hiper_goal,
                'sales' => round($sales, 2),
                'achievement_pct' => $pct,
                'tier' => $tier,
                'award_pct' => $awardPct,
                'award_amount' => $awardAmount,
            ];
        })->sortByDesc('achievement_pct')->values();

        return response()->json(['ranking' => $ranking]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,txt|max:2048',
        ]);

        $import = new StoreGoalsImport(auth()->id());
        Excel::import($import, $request->file('file'));

        $results = $import->getResults();
        $total = $results['created'] + $results['updated'];

        $message = "Importação concluída: {$results['created']} criadas, {$results['updated']} atualizadas.";
        if (!empty($results['errors'])) {
            $message .= ' ' . count($results['errors']) . ' erros encontrados.';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'results' => $results,
            ]);
        }

        return back()->with('success', $message)
            ->with('import_errors', $results['errors']);
    }

    public function exportByStore(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $goals = StoreGoal::forMonth($month, $year)->with('store')->get();

        $rows = [['Loja', 'Meta', 'Super Meta', 'Vendas', 'Atingimento %', 'Dias Úteis']];

        foreach ($goals as $goal) {
            $sales = (float) Sale::forMonth($month, $year)
                ->forStoreWithEcommerce($goal->store_id)
                ->sum('total_sales');
            $pct = $goal->goal_amount > 0 ? round(($sales / $goal->goal_amount) * 100, 1) : 0;

            $rows[] = [
                $goal->store->display_name ?? $goal->store->name,
                number_format($goal->goal_amount, 2, ',', '.'),
                number_format($goal->super_goal, 2, ',', '.'),
                number_format($sales, 2, ',', '.'),
                $pct . '%',
                $goal->business_days,
            ];
        }

        $months = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
                   7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
        $filename = "metas_lojas_{$months[$month]}_{$year}.csv";

        return $this->csvResponse($rows, $filename);
    }

    public function exportByConsultant(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $consultantGoals = ConsultantGoal::with(['employee', 'storeGoal.store'])
            ->forMonth($month, $year)
            ->get();

        $awards = PercentageAward::all()->keyBy('level');

        $rows = [['Consultor', 'Loja', 'Nível', 'Meta Individual', 'Super Meta', 'Hiper Meta', 'Vendas', 'Atingimento %', 'Faixa', 'Comissão %', 'Valor Comissão']];

        foreach ($consultantGoals as $cg) {
            $sales = (float) Sale::forMonth($month, $year)
                ->forEmployee($cg->employee_id)
                ->sum('total_sales');

            $pct = $cg->individual_goal > 0 ? round(($sales / $cg->individual_goal) * 100, 1) : 0;
            $tier = $cg->getAchievementTier($sales);
            $tierLabels = ['below' => 'Abaixo', 'goal' => 'Meta', 'super' => 'Super', 'hiper' => 'Hiper'];
            $award = $awards->get($cg->level_snapshot);
            $awardPct = $award ? $award->getPercentageForTier($tier) : 0;

            $rows[] = [
                $cg->employee?->name ?? 'N/A',
                $cg->storeGoal?->store?->display_name ?? 'N/A',
                $cg->level_snapshot,
                number_format($cg->individual_goal, 2, ',', '.'),
                number_format($cg->super_goal, 2, ',', '.'),
                number_format($cg->hiper_goal, 2, ',', '.'),
                number_format($sales, 2, ',', '.'),
                $pct . '%',
                $tierLabels[$tier] ?? $tier,
                $awardPct . '%',
                number_format($sales * ($awardPct / 100), 2, ',', '.'),
            ];
        }

        $months = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
                   7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
        $filename = "metas_consultores_{$months[$month]}_{$year}.csv";

        return $this->csvResponse($rows, $filename);
    }

    public function loadConfirmData(StoreGoal $storeGoal)
    {
        $storeGoal->load(['store', 'consultantGoals.employee.employeeStatus']);

        $salesByEmployee = Sale::forMonth($storeGoal->reference_month, $storeGoal->reference_year)
            ->forStoreWithEcommerce($storeGoal->store_id)
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($sales) => round((float) $sales->sum('total_sales'), 2));

        $confirmedByEmployee = ConfirmedSale::forStore($storeGoal->store_id)
            ->forMonth($storeGoal->reference_month, $storeGoal->reference_year)
            ->pluck('sale_value', 'employee_id');

        $consultants = $storeGoal->consultantGoals->map(fn ($cg) => [
            'employee_id' => $cg->employee_id,
            'employee_name' => $cg->employee?->name ?? 'N/A',
            'level' => $cg->level_snapshot,
            'status_id' => $cg->employee?->status_id,
            'status_name' => $cg->employee?->employeeStatus?->description_name ?? 'N/A',
            'is_active' => $cg->employee?->status_id === 2,
            'system_sales' => $salesByEmployee->get($cg->employee_id, 0),
            'confirmed_sales' => $confirmedByEmployee->has($cg->employee_id)
                ? round((float) $confirmedByEmployee->get($cg->employee_id), 2)
                : null,
        ])->sortBy('employee_name')->values();

        return response()->json([
            'store_name' => $storeGoal->store->display_name ?? $storeGoal->store->name,
            'period_label' => $storeGoal->period_label,
            'consultants' => $consultants,
        ]);
    }

    public function confirmSales(Request $request, StoreGoal $storeGoal)
    {
        $request->validate([
            'sales' => 'required|array|min:1',
            'sales.*.employee_id' => 'required|integer|exists:employees,id',
            'sales.*.sale_value' => 'nullable|numeric|min:0',
        ]);

        $confirmed = 0;
        $removed = 0;

        DB::transaction(function () use ($request, $storeGoal, &$confirmed, &$removed) {
            foreach ($request->sales as $item) {
                $employeeId = $item['employee_id'];
                $saleValue = $item['sale_value'];

                if ($saleValue === null || $saleValue === '' || (float) $saleValue <= 0) {
                    $deleted = ConfirmedSale::where('employee_id', $employeeId)
                        ->where('store_id', $storeGoal->store_id)
                        ->where('reference_month', $storeGoal->reference_month)
                        ->where('reference_year', $storeGoal->reference_year)
                        ->delete();
                    if ($deleted) {
                        $removed++;
                    }
                } else {
                    ConfirmedSale::updateOrCreate(
                        [
                            'employee_id' => $employeeId,
                            'store_id' => $storeGoal->store_id,
                            'reference_month' => $storeGoal->reference_month,
                            'reference_year' => $storeGoal->reference_year,
                        ],
                        [
                            'sale_value' => (float) $saleValue,
                            'confirmed_by_user_id' => auth()->id(),
                        ]
                    );
                    $confirmed++;
                }
            }
        });

        return response()->json([
            'message' => "Vendas confirmadas: {$confirmed}, removidas: {$removed}.",
            'confirmed' => $confirmed,
            'removed' => $removed,
        ]);
    }

    protected function csvResponse(array $rows, string $filename)
    {
        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($file, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($file, $row, ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
