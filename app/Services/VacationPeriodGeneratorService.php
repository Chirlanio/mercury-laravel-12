<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\VacationPeriod;
use Carbon\Carbon;

class VacationPeriodGeneratorService
{
    public function __construct(
        private VacationCalculationService $calcService,
    ) {}

    /**
     * Gera períodos aquisitivos para um funcionário baseado na data de admissão.
     *
     * @return array{created: int, skipped: int, errors: string[]}
     */
    public function generateForEmployee(int $employeeId): array
    {
        $employee = Employee::find($employeeId);
        if (! $employee || ! $employee->admission_date) {
            return ['created' => 0, 'skipped' => 0, 'errors' => ['Funcionário não encontrado ou sem data de admissão.']];
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        $admissionDate = Carbon::parse($employee->admission_date);
        $currentStart = $admissionDate->copy();
        $today = Carbon::today();

        // Gerar períodos do início até hoje (com margem de 12 meses à frente)
        while ($currentStart->copy()->addYear()->lte($today->copy()->addYear())) {
            $dateStartAcq = $currentStart->format('Y-m-d');
            $dateEndAcq = $currentStart->copy()->addYear()->subDay()->format('Y-m-d');
            $dateLimitConcessive = $currentStart->copy()->addYears(2)->subDay()->format('Y-m-d');

            // Verificar se já existe
            $exists = VacationPeriod::where('employee_id', $employeeId)
                ->where('date_start_acq', $dateStartAcq)
                ->exists();

            if ($exists) {
                $skipped++;
                $currentStart->addYear();

                continue;
            }

            // Determinar status
            $endAcq = Carbon::parse($dateEndAcq);
            $limitConc = Carbon::parse($dateLimitConcessive);

            if ($endAcq->gt($today)) {
                $status = VacationPeriod::STATUS_ACQUIRING;
            } elseif ($limitConc->lt($today)) {
                $status = VacationPeriod::STATUS_EXPIRED;
            } else {
                $status = VacationPeriod::STATUS_AVAILABLE;
            }

            // Calcular faltas injustificadas no período
            $absencesCount = $this->countUnjustifiedAbsences($employeeId, $dateStartAcq, $dateEndAcq);
            $daysEntitled = $this->calcService->calculateDaysEntitledByAbsences($absencesCount);

            VacationPeriod::create([
                'employee_id' => $employeeId,
                'date_start_acq' => $dateStartAcq,
                'date_end_acq' => $dateEndAcq,
                'date_limit_concessive' => $dateLimitConcessive,
                'days_entitled' => $daysEntitled,
                'absences_count' => $absencesCount,
                'status' => $status,
                'created_by_user_id' => auth()->id(),
            ]);

            $created++;
            $currentStart->addYear();
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Gera períodos para todos os funcionários ativos.
     */
    public function generateForAllEmployees(?string $storeId = null): array
    {
        $query = Employee::where('status_id', 2); // Ativos
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $employees = $query->get(['id']);
        $totalCreated = 0;
        $totalSkipped = 0;
        $totalErrors = [];

        foreach ($employees as $employee) {
            $result = $this->generateForEmployee($employee->id);
            $totalCreated += $result['created'];
            $totalSkipped += $result['skipped'];
            $totalErrors = array_merge($totalErrors, $result['errors']);
        }

        return [
            'employees_processed' => $employees->count(),
            'created' => $totalCreated,
            'skipped' => $totalSkipped,
            'errors' => $totalErrors,
        ];
    }

    /**
     * Verifica e atualiza períodos vencidos.
     */
    public function checkExpiredPeriods(): int
    {
        return VacationPeriod::where('status', VacationPeriod::STATUS_AVAILABLE)
            ->where('date_limit_concessive', '<', today())
            ->update(['status' => VacationPeriod::STATUS_EXPIRED]);
    }

    /**
     * Conta faltas injustificadas no período aquisitivo.
     */
    private function countUnjustifiedAbsences(int $employeeId, string $dateStart, string $dateEnd): int
    {
        // Usar model Absence se existir
        if (class_exists(\App\Models\Absence::class)) {
            return \App\Models\Absence::where('employee_id', $employeeId)
                ->where('is_justified', false)
                ->where('is_archived', false)
                ->whereBetween('absence_date', [$dateStart, $dateEnd])
                ->count();
        }

        return 0;
    }
}
