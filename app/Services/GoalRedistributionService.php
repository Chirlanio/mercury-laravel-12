<?php

namespace App\Services;

use App\Models\ConsultantGoal;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\MedicalCertificate;
use App\Models\Store;
use App\Models\StoreGoal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoalRedistributionService
{
    const CONSULTANT_POSITION_ID = 1;
    const MIN_MEDICAL_LEAVE_DAYS = 10;
    const NEW_HIRE_TRAINING_DAYS = 3;

    /**
     * Redistribute a store goal among active consultants.
     *
     * @return array Summary with consultant_count and details
     */
    public function redistribute(StoreGoal $storeGoal): array
    {
        $store = $storeGoal->store;
        if (!$store) {
            Log::warning('GoalRedistribution: Store not found', ['store_goal_id' => $storeGoal->id]);
            return ['consultant_count' => 0, 'message' => 'Loja não encontrada.'];
        }

        $consultants = $this->getEligibleConsultants($store, $storeGoal);

        if ($consultants->isEmpty()) {
            // Remove any existing consultant goals
            ConsultantGoal::where('store_goal_id', $storeGoal->id)->delete();

            return ['consultant_count' => 0, 'message' => 'Nenhum consultor ativo encontrado na loja.'];
        }

        $distribution = $this->calculateDistribution($consultants, $storeGoal);

        DB::transaction(function () use ($storeGoal, $distribution) {
            // Remove existing consultant goals
            ConsultantGoal::where('store_goal_id', $storeGoal->id)->delete();

            // Insert new ones
            foreach ($distribution as $row) {
                ConsultantGoal::create([
                    'store_goal_id' => $storeGoal->id,
                    'employee_id' => $row['employee_id'],
                    'reference_month' => $storeGoal->reference_month,
                    'reference_year' => $storeGoal->reference_year,
                    'working_days' => $row['working_days'],
                    'business_days' => $storeGoal->business_days,
                    'deducted_days' => $row['deducted_days'],
                    'individual_goal' => $row['individual_goal'],
                    'super_goal' => $row['super_goal'],
                    'hiper_goal' => $row['hiper_goal'],
                    'level_snapshot' => $row['level_snapshot'],
                    'weight' => $row['weight'],
                ]);
            }
        });

        return [
            'consultant_count' => count($distribution),
            'message' => count($distribution) . ' consultores redistribuídos.',
        ];
    }

    /**
     * Get consultants eligible for goal distribution.
     */
    protected function getEligibleConsultants(Store $store, StoreGoal $storeGoal): Collection
    {
        // Find active contracts for consultants in this store
        $contracts = EmploymentContract::active()
            ->byStore($store->code)
            ->byPosition(self::CONSULTANT_POSITION_ID)
            ->with('employee')
            ->get();

        $monthStart = Carbon::create($storeGoal->reference_year, $storeGoal->reference_month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return $contracts->map(function ($contract) use ($storeGoal, $monthStart, $monthEnd) {
            $employee = $contract->employee;
            if (!$employee) {
                return null;
            }

            $level = $employee->level ?: 'Pleno';
            $weight = ConsultantGoal::getWeightForLevel($level);

            // Calculate deductions
            $deductedDays = $this->calculateDeductions($employee, $monthStart, $monthEnd);

            $effectiveDays = max(0, $storeGoal->business_days - $deductedDays);

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'level' => $level,
                'weight' => $weight,
                'effective_days' => $effectiveDays,
                'deducted_days' => $deductedDays,
            ];
        })->filter()->values();
    }

    /**
     * Calculate day deductions for an employee in a given month.
     */
    protected function calculateDeductions(Employee $employee, Carbon $monthStart, Carbon $monthEnd): int
    {
        $deducted = 0;

        // Medical leave >= 10 days that overlaps this month
        $medicalCerts = MedicalCertificate::forEmployee($employee->id)
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where('end_date', '>=', $monthStart->toDateString())
            ->get();

        foreach ($medicalCerts as $cert) {
            $certStart = Carbon::parse($cert->start_date);
            $certEnd = Carbon::parse($cert->end_date);
            $totalDays = $certStart->diffInDays($certEnd) + 1;

            if ($totalDays >= self::MIN_MEDICAL_LEAVE_DAYS) {
                // Calculate overlap with this month
                $overlapStart = $certStart->max($monthStart);
                $overlapEnd = $certEnd->min($monthEnd);
                $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
                $deducted += $overlapDays;
            }
        }

        // New hire training days (admitted in the same month)
        if ($employee->admission_date) {
            $admissionDate = Carbon::parse($employee->admission_date);
            if ($admissionDate->month === $monthStart->month && $admissionDate->year === $monthStart->year) {
                $deducted += self::NEW_HIRE_TRAINING_DAYS;
            }
        }

        return $deducted;
    }

    /**
     * Calculate individual goal distribution.
     */
    protected function calculateDistribution(Collection $consultants, StoreGoal $storeGoal): array
    {
        // Calculate weighted contributions
        $weightedContributions = $consultants->map(function ($c) use ($storeGoal) {
            $contribution = ($c['effective_days'] * $c['weight']);
            return array_merge($c, ['contribution' => $contribution]);
        });

        $totalPool = $weightedContributions->sum('contribution');

        // If everyone has 0 effective days, distribute equally
        if ($totalPool <= 0) {
            $count = $consultants->count();
            $equalGoal = round($storeGoal->goal_amount / $count, 2);

            return $consultants->map(function ($c) use ($equalGoal, $storeGoal) {
                return $this->buildRow($c, $equalGoal, $storeGoal);
            })->all();
        }

        $goalAmount = (float) $storeGoal->goal_amount;
        $distributed = [];
        $sumSoFar = 0;
        $items = $weightedContributions->values()->all();
        $lastIndex = count($items) - 1;

        foreach ($items as $i => $c) {
            if ($i === $lastIndex) {
                // Last consultant gets the remainder
                $individualGoal = round($goalAmount - $sumSoFar, 2);
            } else {
                $individualGoal = round(($c['contribution'] / $totalPool) * $goalAmount, 2);
                $sumSoFar += $individualGoal;
            }

            $distributed[] = $this->buildRow($c, $individualGoal, $storeGoal);
        }

        return $distributed;
    }

    /**
     * Build a consultant goal row.
     */
    protected function buildRow(array $consultant, float $individualGoal, StoreGoal $storeGoal): array
    {
        $superGoal = round($individualGoal * ConsultantGoal::SUPER_MULTIPLIER, 2);
        $hiperGoal = round($individualGoal * ConsultantGoal::HIPER_MULTIPLIER, 2);

        return [
            'employee_id' => $consultant['employee_id'],
            'working_days' => $consultant['effective_days'],
            'deducted_days' => $consultant['deducted_days'],
            'individual_goal' => $individualGoal,
            'super_goal' => $superGoal,
            'hiper_goal' => $hiperGoal,
            'level_snapshot' => $consultant['level'],
            'weight' => $consultant['weight'],
        ];
    }
}
