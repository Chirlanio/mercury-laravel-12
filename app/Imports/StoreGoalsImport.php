<?php

namespace App\Imports;

use App\Models\Store;
use App\Models\StoreGoal;
use App\Services\GoalRedistributionService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StoreGoalsImport implements ToCollection, WithHeadingRow
{
    protected array $results = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    protected int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->count() > 200) {
            $this->results['errors'][] = 'Limite máximo de 200 linhas por arquivo.';
            return;
        }

        $redistributionService = new GoalRedistributionService();
        $storeCache = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // +2 because heading row is 1, data starts at 2

            $storeCode = trim($row['codigo_loja'] ?? $row['store_code'] ?? '');
            $month = (int) ($row['mes'] ?? $row['month'] ?? 0);
            $year = (int) ($row['ano'] ?? $row['year'] ?? 0);
            $goalAmount = $this->parseMoneyValue($row['meta'] ?? $row['goal_amount'] ?? $row['goal_value'] ?? '');
            $businessDays = (int) ($row['dias_uteis'] ?? $row['business_days'] ?? 26);
            $nonWorkingDays = (int) ($row['feriados'] ?? $row['holidays'] ?? $row['non_working_days'] ?? 0);

            // Validate store
            if (empty($storeCode)) {
                $this->results['errors'][] = "Linha {$rowNum}: Código da loja é obrigatório.";
                continue;
            }

            if (!isset($storeCache[$storeCode])) {
                $storeCache[$storeCode] = Store::where('code', $storeCode)->first();
            }

            $store = $storeCache[$storeCode];
            if (!$store) {
                $this->results['errors'][] = "Linha {$rowNum}: Loja '{$storeCode}' não encontrada.";
                continue;
            }

            // Validate month/year
            if ($month < 1 || $month > 12) {
                $this->results['errors'][] = "Linha {$rowNum}: Mês inválido ({$month}).";
                continue;
            }

            if ($year < 2020 || $year > 2099) {
                $this->results['errors'][] = "Linha {$rowNum}: Ano inválido ({$year}).";
                continue;
            }

            // Validate goal amount
            if ($goalAmount <= 0) {
                $this->results['errors'][] = "Linha {$rowNum}: Valor da meta deve ser maior que zero.";
                continue;
            }

            // Validate business days
            if ($businessDays < 1 || $businessDays > 31) {
                $this->results['errors'][] = "Linha {$rowNum}: Dias úteis inválido ({$businessDays}).";
                continue;
            }

            // Upsert
            $existing = StoreGoal::where('store_id', $store->id)
                ->where('reference_month', $month)
                ->where('reference_year', $year)
                ->first();

            if ($existing) {
                $existing->update([
                    'goal_amount' => $goalAmount,
                    'super_goal' => StoreGoal::calculateSuperGoal($goalAmount),
                    'business_days' => $businessDays,
                    'non_working_days' => $nonWorkingDays,
                    'updated_by_user_id' => $this->userId,
                ]);
                $redistributionService->redistribute($existing);
                $this->results['updated']++;
            } else {
                $storeGoal = StoreGoal::create([
                    'store_id' => $store->id,
                    'reference_month' => $month,
                    'reference_year' => $year,
                    'goal_amount' => $goalAmount,
                    'super_goal' => StoreGoal::calculateSuperGoal($goalAmount),
                    'business_days' => $businessDays,
                    'non_working_days' => $nonWorkingDays,
                    'created_by_user_id' => $this->userId,
                ]);
                $redistributionService->redistribute($storeGoal);
                $this->results['created']++;
            }
        }
    }

    /**
     * Parse Brazilian money format or plain number.
     * Handles: "150.000,00", "150000.00", "150000", "150.000"
     */
    protected function parseMoneyValue(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = (string) $value;
        $value = trim($value);
        $value = str_replace(['R$', ' '], '', $value);

        // Brazilian format: 150.000,00
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            // Could be "150000,00" or "1,500"
            $parts = explode(',', $value);
            if (strlen(end($parts)) === 2) {
                $value = str_replace(',', '.', $value);
            }
        }

        return (float) $value;
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
