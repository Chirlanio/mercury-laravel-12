<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Vacation;
use App\Models\VacationPeriod;

class VacationValidatorService
{
    private array $errors = [];

    private array $warnings = [];

    public function __construct(
        private VacationCalculationService $calcService,
    ) {}

    /**
     * Validação completa para férias normais.
     *
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public function validateAll(array $data, ?int $excludeVacationId = null): array
    {
        $this->errors = [];
        $this->warnings = [];

        $this->validateEmployeeStatus($data);
        $this->validatePeriodBalance($data, $excludeVacationId);
        $this->validateMinDays($data);
        $this->validateMaxInstallments($data, $excludeVacationId);
        $this->validateOverlap($data, $excludeVacationId);
        $this->validateBlackoutDates($data);
        $this->validateAdvanceNotice($data);
        $this->validateSellAllowance($data);
        $this->validateDefaultDaysOverride($data);
        $this->validateMinorRestrictions($data);
        $this->validateConcessivePeriod($data);

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Validação para férias retroativas (regras mais flexíveis).
     */
    public function validateRetroactive(array $data, ?int $excludeVacationId = null): array
    {
        $this->errors = [];
        $this->warnings = [];

        $this->validateEmployeeStatus($data);
        $this->validatePeriodBalance($data, $excludeVacationId);
        $this->validateMinDays($data);
        $this->validateMaxInstallments($data, $excludeVacationId);
        $this->validateOverlap($data, $excludeVacationId);
        $this->validateSellAllowance($data);

        if (empty($data['retroactive_reason'])) {
            $this->errors[] = 'Justificativa é obrigatória para férias retroativas.';
        }

        if (! empty($data['date_start']) && strtotime($data['date_start']) >= strtotime('today')) {
            $this->errors[] = 'Data de início deve ser no passado para férias retroativas.';
        }

        // Data de fim também deve estar no passado (período inteiro já concluído)
        if (! empty($data['date_end']) && strtotime($data['date_end']) >= strtotime('today')) {
            $this->errors[] = 'O período de férias retroativas deve estar inteiramente no passado. A data de término ('.date('d/m/Y', strtotime($data['date_end'])).') ainda não passou.';
        }

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * 1. Funcionário deve estar ativo.
     */
    private function validateEmployeeStatus(array $data): void
    {
        if (empty($data['employee_id'])) {
            $this->errors[] = 'Funcionário é obrigatório.';

            return;
        }

        $employee = Employee::find($data['employee_id']);
        if (! $employee) {
            $this->errors[] = 'Funcionário não encontrado.';

            return;
        }

        if ($employee->status_id !== 2) {
            $this->errors[] = 'Funcionário não está ativo. Status atual: '.($employee->employeeStatus?->description_name ?? 'desconhecido').'.';
        }
    }

    /**
     * 2. Saldo do período deve ser suficiente.
     */
    private function validatePeriodBalance(array $data, ?int $excludeId): void
    {
        if (empty($data['vacation_period_id'])) {
            $this->errors[] = 'Período aquisitivo é obrigatório.';

            return;
        }

        $balance = $this->calcService->calculateBalance($data['vacation_period_id']);
        $requested = ($data['days_quantity'] ?? 0) + ($data['sell_days'] ?? 0);

        // Se está editando, adicionar os dias da solicitação atual de volta
        if ($excludeId) {
            $existing = Vacation::find($excludeId);
            if ($existing && $existing->vacation_period_id == $data['vacation_period_id']) {
                $balance['balance'] += $existing->days_quantity;
            }
        }

        if ($requested > $balance['balance']) {
            $this->errors[] = "Saldo insuficiente. Disponível: {$balance['balance']} dias. Solicitado: {$requested} dias.";
        }
    }

    /**
     * 3. Mínimo de dias por parcela (Art. 134 §1 CLT).
     */
    private function validateMinDays(array $data): void
    {
        $days = $data['days_quantity'] ?? 0;

        if ($days < 5) {
            $this->errors[] = 'Mínimo de 5 dias por parcela (Art. 134 §1 CLT).';

            return;
        }

        // Primeira parcela deve ter pelo menos 14 dias
        $installment = $data['installment'] ?? 1;
        if ($installment === 1 && $days < 14) {
            $period = VacationPeriod::find($data['vacation_period_id'] ?? 0);
            if ($period && $period->days_balance >= 14) {
                $this->errors[] = 'A primeira parcela deve ter no mínimo 14 dias consecutivos (Art. 134 §1 CLT).';
            }
        }
    }

    /**
     * 4. Máximo de 3 parcelas por período (Art. 134 §1 CLT).
     */
    private function validateMaxInstallments(array $data, ?int $excludeId): void
    {
        if (empty($data['vacation_period_id'])) {
            return;
        }

        $installments = $this->calcService->calculateRemainingInstallments($data['vacation_period_id']);
        $requestedInstallment = $data['installment'] ?? 1;

        // Se está editando, não contar a parcela atual
        if ($excludeId) {
            $existing = Vacation::find($excludeId);
            if ($existing) {
                $installments['used'] = array_values(array_diff($installments['used'], [$existing->installment]));
            }
        }

        if (in_array($requestedInstallment, $installments['used'])) {
            $this->errors[] = "Parcela {$requestedInstallment} já foi utilizada neste período.";
        }

        if (count($installments['used']) >= 3 && ! in_array($requestedInstallment, $installments['used'])) {
            $this->errors[] = 'Máximo de 3 parcelas por período aquisitivo (Art. 134 §1 CLT).';
        }
    }

    /**
     * 5. Não pode ter sobreposição de datas.
     */
    private function validateOverlap(array $data, ?int $excludeId): void
    {
        if (empty($data['employee_id']) || empty($data['date_start']) || empty($data['date_end'])) {
            return;
        }

        $overlap = Vacation::forEmployee($data['employee_id'])
            ->overlapping($data['date_start'], $data['date_end'], $excludeId)
            ->exists();

        if ($overlap) {
            $this->errors[] = 'Já existe uma solicitação de férias neste período.';
        }
    }

    /**
     * 6. Validação de blackout dates (Art. 134 §3 CLT).
     */
    private function validateBlackoutDates(array $data): void
    {
        if (empty($data['date_start'])) {
            return;
        }

        if (! $this->calcService->isValidStartDate($data['date_start'])) {
            $suggested = $this->calcService->suggestNextValidDate($data['date_start']);
            $this->errors[] = 'Início das férias não permitido nesta data (Art. 134 §3 CLT: não pode iniciar em fim de semana, feriado ou véspera). Data sugerida: '.date('d/m/Y', strtotime($suggested)).'.';
        }
    }

    /**
     * 7. Antecedência mínima de 30 dias (Art. 135 CLT).
     */
    private function validateAdvanceNotice(array $data): void
    {
        if (empty($data['date_start'])) {
            return;
        }

        $daysUntil = (int) ((strtotime($data['date_start']) - strtotime('today')) / 86400);

        if ($daysUntil < 0) {
            $this->errors[] = 'Data de início deve ser futura.';

            return;
        }

        if ($daysUntil < 30) {
            $this->warnings[] = "Antecedência mínima recomendada de 30 dias (Art. 135 CLT). Faltam {$daysUntil} dias.";
        }
    }

    /**
     * 8. Abono pecuniário máximo 1/3 (Art. 143 CLT).
     */
    private function validateSellAllowance(array $data): void
    {
        $sellDays = $data['sell_days'] ?? 0;
        if ($sellDays <= 0) {
            return;
        }

        if (empty($data['vacation_period_id'])) {
            return;
        }

        $period = VacationPeriod::find($data['vacation_period_id']);
        if (! $period) {
            return;
        }

        $maxSellDays = (int) floor($period->days_entitled / 3);
        $alreadySold = $period->sell_days;

        $totalSell = $alreadySold + $sellDays;
        if ($totalSell > $maxSellDays) {
            $remaining = max(0, $maxSellDays - $alreadySold);
            $this->errors[] = "Abono pecuniário máximo de 1/3 dos dias de direito ({$maxSellDays} dias). Já vendidos: {$alreadySold}. Disponível para venda: {$remaining} dias (Art. 143 CLT).";
        }
    }

    /**
     * 9. Dias fora do padrão requerem justificativa.
     */
    private function validateDefaultDaysOverride(array $data): void
    {
        if (! ($data['default_days_override'] ?? false)) {
            return;
        }

        if (empty($data['override_reason'])) {
            $this->errors[] = 'Justificativa é obrigatória para alterar o padrão de dias.';
        }
    }

    /**
     * 10. Menores de 18 anos devem gozar férias escolares (Art. 136 CLT).
     */
    private function validateMinorRestrictions(array $data): void
    {
        if (empty($data['employee_id']) || empty($data['date_start'])) {
            return;
        }

        $employee = Employee::find($data['employee_id']);
        if (! $employee || ! $employee->birth_date) {
            return;
        }

        $age = $employee->birth_date->diffInYears(now());
        if ($age >= 18) {
            return;
        }

        $month = (int) date('m', strtotime($data['date_start']));
        $schoolBreakMonths = [1, 2, 7]; // Janeiro, Fevereiro, Julho

        if (! in_array($month, $schoolBreakMonths)) {
            $this->warnings[] = 'Funcionário menor de 18 anos deve gozar férias durante as férias escolares (Jan, Fev ou Jul - Art. 136 CLT).';
        }
    }

    /**
     * 11. Alerta se período concessivo está vencido ou próximo (Art. 137 CLT).
     */
    private function validateConcessivePeriod(array $data): void
    {
        if (empty($data['vacation_period_id'])) {
            return;
        }

        $period = VacationPeriod::find($data['vacation_period_id']);
        if (! $period) {
            return;
        }

        $daysUntilExpiry = (int) now()->diffInDays($period->date_limit_concessive, false);

        if ($daysUntilExpiry < 0) {
            $this->warnings[] = 'ATENÇÃO: Período concessivo já vencido! Risco de férias em dobro (Art. 137 CLT).';
        } elseif ($daysUntilExpiry <= 60) {
            $this->warnings[] = "Período concessivo vence em {$daysUntilExpiry} dias (".$period->date_limit_concessive->format('d/m/Y').').';
        }
    }
}
