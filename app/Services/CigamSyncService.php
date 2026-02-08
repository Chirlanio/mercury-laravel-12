<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Sale;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CigamSyncService
{
    protected array $storeCodeMap = [];
    protected array $employeeCpfMap = [];

    public function isAvailable(): bool
    {
        try {
            DB::connection('cigam')->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function syncDateRange(Carbon $start, Carbon $end, ?int $storeId = null): array
    {
        $result = ['inserted' => 0, 'updated' => 0, 'errors' => 0, 'error_messages' => []];

        if (!$this->isAvailable()) {
            $result['error_messages'][] = 'Conexão CIGAM não disponível.';
            return $result;
        }

        $this->loadMappings();

        try {
            $query = DB::connection('cigam')
                ->table('msl_fmovimentodiario_')
                ->select(
                    DB::raw("data AS date_sales"),
                    DB::raw("filial AS store_code"),
                    DB::raw("cpf"),
                    DB::raw("SUM(CASE WHEN controle = 2 THEN valor_realizado WHEN controle = 6 AND ent_sai = 'E' THEN -valor_realizado ELSE 0 END) AS total_sales"),
                    DB::raw("SUM(CASE WHEN controle = 2 THEN qtde WHEN controle = 6 AND ent_sai = 'E' THEN -qtde ELSE 0 END) AS qtde_total")
                )
                ->where(function ($q) {
                    $q->where('controle', 2)
                      ->orWhere(function ($q2) {
                          $q2->where('controle', 6)->where('ent_sai', 'E');
                      });
                })
                ->whereBetween('data', [$start->toDateString(), $end->toDateString()])
                ->groupBy('data', 'filial', 'cpf');

            if ($storeId) {
                $store = Store::find($storeId);
                if ($store) {
                    $query->where('filial', $store->code);
                }
            }

            $records = $query->get();

            foreach ($records as $record) {
                try {
                    $resolvedStoreId = $this->resolveStoreId($record->store_code, $record->cpf);
                    $resolvedEmployeeId = $this->resolveEmployeeId($record->cpf);

                    if (!$resolvedStoreId || !$resolvedEmployeeId) {
                        $result['errors']++;
                        continue;
                    }

                    $existing = Sale::where('store_id', $resolvedStoreId)
                        ->where('employee_id', $resolvedEmployeeId)
                        ->where('date_sales', $record->date_sales)
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'total_sales' => $record->total_sales,
                            'qtde_total' => (int) $record->qtde_total,
                            'source' => 'cigam',
                            'user_hash' => md5($record->cpf . $record->store_code . $record->date_sales),
                            'updated_by_user_id' => auth()->id(),
                        ]);
                        $result['updated']++;
                    } else {
                        Sale::create([
                            'store_id' => $resolvedStoreId,
                            'employee_id' => $resolvedEmployeeId,
                            'date_sales' => $record->date_sales,
                            'total_sales' => $record->total_sales,
                            'qtde_total' => (int) $record->qtde_total,
                            'source' => 'cigam',
                            'user_hash' => md5($record->cpf . $record->store_code . $record->date_sales),
                            'created_by_user_id' => auth()->id(),
                            'updated_by_user_id' => auth()->id(),
                        ]);
                        $result['inserted']++;
                    }
                } catch (\Exception $e) {
                    $result['errors']++;
                    Log::warning('CIGAM sync record error: ' . $e->getMessage(), [
                        'store_code' => $record->store_code,
                        'cpf' => $record->cpf,
                        'date' => $record->date_sales,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('CIGAM sync error: ' . $e->getMessage());
            $result['error_messages'][] = 'Erro na sincronização: ' . $e->getMessage();
        }

        return $result;
    }

    protected function loadMappings(): void
    {
        $this->storeCodeMap = Store::pluck('id', 'code')->toArray();
        $this->employeeCpfMap = Employee::pluck('id', 'cpf')->toArray();
    }

    protected function resolveStoreId(string $storeCode, string $cpf): ?int
    {
        // E-commerce Z441: use employee's contract store
        if ($storeCode === 'Z441') {
            $employeeId = $this->resolveEmployeeId($cpf);
            if ($employeeId) {
                $employee = Employee::with('currentContract')->find($employeeId);
                if ($employee && $employee->currentContract) {
                    $contractStoreCode = $employee->currentContract->store_id ?? null;
                    if ($contractStoreCode && isset($this->storeCodeMap[$contractStoreCode])) {
                        return $this->storeCodeMap[$contractStoreCode];
                    }
                }
            }
            // Fallback to Z441 itself
            return $this->storeCodeMap['Z441'] ?? null;
        }

        return $this->storeCodeMap[$storeCode] ?? null;
    }

    protected function resolveEmployeeId(string $cpf): ?int
    {
        return $this->employeeCpfMap[$cpf] ?? null;
    }
}
