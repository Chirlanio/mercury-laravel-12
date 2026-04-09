<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Vacation;
use App\Models\VacationPeriod;

class VacationCalculationService
{
    public const DEFAULT_DAYS_MANAGER = 15;

    public const DEFAULT_DAYS_EMPLOYEE = 30;

    public const MANAGER_POSITION_ID = 23;

    /**
     * Calcula data de fim: start + (days - 1).
     */
    public function calculateEndDate(string $dateStart, int $daysQuantity): string
    {
        return date('Y-m-d', strtotime($dateStart.' + '.($daysQuantity - 1).' days'));
    }

    /**
     * Calcula data de retorno: próximo dia útil após date_end.
     */
    public function calculateReturnDate(string $dateEnd): string
    {
        $date = date('Y-m-d', strtotime($dateEnd.' +1 day'));

        return $this->nextBusinessDay($date);
    }

    /**
     * Calcula prazo de pagamento: 2 dias úteis antes do início (Art. 145 CLT).
     */
    public function calculatePaymentDeadline(string $dateStart): string
    {
        $date = $dateStart;
        $businessDays = 0;

        while ($businessDays < 2) {
            $date = date('Y-m-d', strtotime($date.' -1 day'));
            if ($this->isBusinessDay($date)) {
                $businessDays++;
            }
        }

        return $date;
    }

    /**
     * Calcula saldo do período aquisitivo.
     */
    public function calculateBalance(int $periodId): array
    {
        $period = VacationPeriod::findOrFail($periodId);

        $pendingDays = Vacation::where('vacation_period_id', $periodId)
            ->notCancelledOrRejected()
            ->whereNotIn('status', [Vacation::STATUS_COMPLETED])
            ->sum('days_quantity');

        $pendingSell = Vacation::where('vacation_period_id', $periodId)
            ->notCancelledOrRejected()
            ->whereNotIn('status', [Vacation::STATUS_COMPLETED])
            ->sum('sell_days');

        return [
            'days_entitled' => $period->days_entitled,
            'days_taken' => $period->days_taken,
            'sell_days' => $period->sell_days,
            'days_requested' => (int) $pendingDays,
            'sell_days_requested' => (int) $pendingSell,
            'balance' => $period->days_entitled - $period->days_taken - $period->sell_days - (int) $pendingDays,
        ];
    }

    /**
     * Calcula parcelas restantes para um período.
     */
    public function calculateRemainingInstallments(int $periodId): array
    {
        $used = Vacation::where('vacation_period_id', $periodId)
            ->notCancelledOrRejected()
            ->pluck('installment')
            ->toArray();

        $all = [1, 2, 3];
        $remaining = array_values(array_diff($all, $used));

        return [
            'used' => $used,
            'remaining' => $remaining,
            'max' => 3,
        ];
    }

    /**
     * Calcula dias de direito com base em faltas injustificadas (Art. 130 CLT).
     *
     * 0-5 faltas  → 30 dias
     * 6-14 faltas → 24 dias
     * 15-23 faltas → 18 dias
     * 24-32 faltas → 12 dias
     * 33+ faltas  → 0 dias
     */
    public function calculateDaysEntitledByAbsences(int $absences): int
    {
        return match (true) {
            $absences <= 5 => 30,
            $absences <= 14 => 24,
            $absences <= 23 => 18,
            $absences <= 32 => 12,
            default => 0,
        };
    }

    /**
     * Sugere próxima data válida (sem blackout).
     */
    public function suggestNextValidDate(string $date): string
    {
        $current = $date;

        for ($i = 0; $i < 30; $i++) {
            if ($this->isValidStartDate($current)) {
                return $current;
            }
            $current = date('Y-m-d', strtotime($current.' +1 day'));
        }

        return $current;
    }

    /**
     * Retorna o primeiro dia útil de um mês.
     */
    public function getFirstBusinessDay(int $month, int $year): string
    {
        $date = sprintf('%04d-%02d-01', $year, $month);

        while (! $this->isBusinessDay($date)) {
            $date = date('Y-m-d', strtotime($date.' +1 day'));
        }

        return $date;
    }

    /**
     * Retorna dias padrão por cargo.
     */
    public function getDefaultDaysByPosition(int $positionId): int
    {
        return $positionId === self::MANAGER_POSITION_ID
            ? self::DEFAULT_DAYS_MANAGER
            : self::DEFAULT_DAYS_EMPLOYEE;
    }

    /**
     * Verifica se uma data é válida para início de férias (Art. 134 §3 CLT).
     */
    public function isValidStartDate(string $date): bool
    {
        $dayOfWeek = (int) date('N', strtotime($date));

        // Não pode iniciar em sábado (6) ou domingo (7)
        if ($dayOfWeek >= 6) {
            return false;
        }

        // Não pode iniciar em feriado
        if (Holiday::isHoliday($date)) {
            return false;
        }

        // Não pode iniciar véspera de feriado
        $nextDay = date('Y-m-d', strtotime($date.' +1 day'));
        if (Holiday::isHoliday($nextDay)) {
            return false;
        }

        // Não pode iniciar véspera de descanso (sexta antes de sábado)
        $nextDayOfWeek = (int) date('N', strtotime($nextDay));
        if ($nextDayOfWeek >= 6) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se é dia útil (não é fim de semana nem feriado).
     */
    public function isBusinessDay(string $date): bool
    {
        $dayOfWeek = (int) date('N', strtotime($date));
        if ($dayOfWeek >= 6) {
            return false;
        }

        return ! Holiday::isHoliday($date);
    }

    /**
     * Próximo dia útil a partir de uma data.
     */
    public function nextBusinessDay(string $date): string
    {
        while (! $this->isBusinessDay($date)) {
            $date = date('Y-m-d', strtotime($date.' +1 day'));
        }

        return $date;
    }
}
