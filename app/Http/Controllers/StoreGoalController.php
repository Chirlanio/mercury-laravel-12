<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Imports\StoreGoalsImport;
use App\Models\ConsultantGoal;
use App\Models\PercentageAward;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreGoal;
use App\Services\GoalRedistributionService;
use Illuminate\Http\Request;
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

        $goals = $query->orderBy('store_id')->get()->map(fn ($goal) => [
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
        $storeGoal->load(['store', 'consultantGoals.employee', 'createdBy', 'updatedBy']);

        // Get sales data for achievement calculation
        $salesByEmployee = Sale::forMonth($storeGoal->reference_month, $storeGoal->reference_year)
            ->forStoreWithEcommerce($storeGoal->store_id)
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($sales) => round((float) $sales->sum('total_sales'), 2));

        $totalSales = $salesByEmployee->sum();

        $consultants = $storeGoal->consultantGoals->map(function ($cg) use ($salesByEmployee) {
            $actualSales = $salesByEmployee->get($cg->employee_id, 0);
            $achievementPct = $cg->individual_goal > 0
                ? round(($actualSales / $cg->individual_goal) * 100, 1)
                : 0;

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
                'actual_sales' => $actualSales,
                'achievement_pct' => $achievementPct,
                'tier' => $cg->getAchievementTier($actualSales),
            ];
        })->sortByDesc('actual_sales')->values();

        $goalAchievementPct = $storeGoal->goal_amount > 0
            ? round(($totalSales / $storeGoal->goal_amount) * 100, 1)
            : 0;

        return response()->json([
            'id' => $storeGoal->id,
            'store_name' => $storeGoal->store->display_name ?? $storeGoal->store->name,
            'store_id' => $storeGoal->store_id,
            'reference_month' => $storeGoal->reference_month,
            'reference_year' => $storeGoal->reference_year,
            'period_label' => $storeGoal->period_label,
            'goal_amount' => (float) $storeGoal->goal_amount,
            'super_goal' => (float) $storeGoal->super_goal,
            'business_days' => $storeGoal->business_days,
            'non_working_days' => $storeGoal->non_working_days,
            'total_sales' => $totalSales,
            'achievement_pct' => $goalAchievementPct,
            'consultants' => $consultants,
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
        $totalSales = 0;
        $storesAboveGoal = 0;
        $storesAboveSuper = 0;
        $storeRanking = [];

        foreach ($goals as $goal) {
            $storeSales = (float) Sale::forMonth($month, $year)
                ->forStoreWithEcommerce($goal->store_id)
                ->sum('total_sales');
            $totalSales += $storeSales;

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
