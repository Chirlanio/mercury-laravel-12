<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Movement;
use App\Models\MovementSyncLog;
use App\Models\MovementType;
use App\Models\Sale;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MovementSyncService
{
    const BATCH_INSERT_SIZE = 500;
    const MAX_CHUNK_DAYS = 7;
    const MAX_RANGE_DAYS = 180;

    protected CigamSyncService $cigamService;
    protected array $storeCodeMap = [];
    protected array $employeeCpfMap = [];

    public function __construct(CigamSyncService $cigamService)
    {
        $this->cigamService = $cigamService;
    }

    public function isAvailable(): bool
    {
        return $this->cigamService->isAvailable();
    }

    public function getUnavailableReason(): ?string
    {
        return $this->cigamService->getUnavailableReason();
    }

    // =============================================
    // SYNC METHODS
    // =============================================

    public function syncAutomatic(?int $userId = null, ?MovementSyncLog $existingLog = null): MovementSyncLog
    {
        $log = $existingLog ?? MovementSyncLog::start('auto', $userId);

        if (! $this->isAvailable()) {
            $log->markFailed($this->getUnavailableReason() ?? 'CIGAM indisponível');

            return $log;
        }

        try {
            $lastDate = Movement::max('movement_date');
            $start = $lastDate ? Carbon::parse($lastDate) : now()->subDays(31);
            $end = now()->subDay()->endOfDay();

            if ($start->gt($end)) {
                $log->update(['total_records' => 0]);
                $log->markCompleted();

                return $log;
            }

            $log->update([
                'date_range_start' => $start->toDateString(),
                'date_range_end' => $end->toDateString(),
            ]);

            $this->syncDateRangeInternal($start, $end, $log, deleteFirst: true);
            $this->refreshSalesSummary($start, $end);

            $log->markCompleted();
        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('MovementSync auto failed', ['error' => $e->getMessage()]);
        }

        return $log;
    }

    public function syncToday(?int $userId = null, ?MovementSyncLog $existingLog = null): MovementSyncLog
    {
        $log = $existingLog ?? MovementSyncLog::start('today', $userId);

        if (! $this->isAvailable()) {
            $log->markFailed($this->getUnavailableReason() ?? 'CIGAM indisponível');

            return $log;
        }

        try {
            $today = now()->toDateString();
            $lastTime = Movement::forDate($today)->max('movement_time');

            $log->update([
                'date_range_start' => $today,
                'date_range_end' => $today,
            ]);

            $countQuery = DB::connection('cigam')
                ->table('msl_fmovimentodiario_')
                ->where('data', $today);

            if ($lastTime) {
                $countQuery->where('hora', '>', $lastTime);
            }

            $totalCount = $countQuery->count();
            $log->update(['total_records' => $totalCount]);

            if ($totalCount === 0) {
                $log->markCompleted();

                return $log;
            }

            $cursorQuery = DB::connection('cigam')
                ->table('msl_fmovimentodiario_')
                ->where('data', $today);

            if ($lastTime) {
                $cursorQuery->where('hora', '>', $lastTime);
            }

            $cursor = $cursorQuery->orderBy('hora')->cursor();

            $batchId = (string) Str::uuid();
            $batch = [];

            foreach ($cursor as $record) {
                $batch[] = $this->mapCigamRecord($record, $batchId);

                if (count($batch) >= self::BATCH_INSERT_SIZE) {
                    DB::table('movements')->insert($batch);
                    $log->increment('inserted_records', count($batch));
                    $log->increment('processed_records', count($batch));
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                DB::table('movements')->insert($batch);
                $log->increment('inserted_records', count($batch));
                $log->increment('processed_records', count($batch));
            }

            $this->refreshSalesSummary(Carbon::parse($today), Carbon::parse($today));
            $log->markCompleted();
        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('MovementSync today failed', ['error' => $e->getMessage()]);
        }

        return $log;
    }

    public function syncByMonth(int $month, int $year, ?int $userId = null, ?MovementSyncLog $existingLog = null): MovementSyncLog
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $this->syncByDateRange($start, $end, $userId, $existingLog);
    }

    public function syncByDateRange(Carbon $start, Carbon $end, ?int $userId = null, ?MovementSyncLog $existingLog = null): MovementSyncLog
    {
        $log = $existingLog ?? MovementSyncLog::start('range', $userId);

        if (! $this->isAvailable()) {
            $log->markFailed($this->getUnavailableReason() ?? 'CIGAM indisponível');

            return $log;
        }

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $log->markFailed("Período máximo de " . self::MAX_RANGE_DAYS . " dias excedido.");

            return $log;
        }

        try {
            $log->update([
                'date_range_start' => $start->toDateString(),
                'date_range_end' => $end->toDateString(),
            ]);

            $this->syncDateRangeInternal($start, $end, $log, deleteFirst: true);
            $this->refreshSalesSummary($start, $end);
            $log->markCompleted();
        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('MovementSync range failed', ['error' => $e->getMessage()]);
        }

        return $log;
    }

    // =============================================
    // SALES SUMMARY REFRESH
    // =============================================

    public function refreshSalesSummary(Carbon $start, Carbon $end): array
    {
        $this->loadMappings();

        $rows = DB::table('movements')
            ->select(
                'movement_date',
                'store_code',
                DB::raw("COALESCE(NULLIF(TRIM(cpf_consultant), ''), '00000000000') as cpf"),
                DB::raw("SUM(CASE WHEN movement_code = 6 AND entry_exit = 'E' THEN -realized_value ELSE realized_value END) as total_sales"),
                DB::raw("CAST(SUM(CASE WHEN movement_code = 6 AND entry_exit = 'E' THEN -quantity ELSE quantity END) AS SIGNED) as qtde_total"),
            )
            ->where(function ($q) {
                $q->where('movement_code', 2)
                  ->orWhere(function ($q2) {
                      $q2->where('movement_code', 6)->where('entry_exit', 'E');
                  });
            })
            ->whereBetween('movement_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('movement_date', 'store_code', DB::raw("COALESCE(NULLIF(TRIM(cpf_consultant), ''), '00000000000')"))
            ->get();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $storeId = $this->storeCodeMap[$row->store_code] ?? null;
            $employeeId = $this->employeeCpfMap[$row->cpf] ?? null;

            if (! $storeId) {
                $skipped++;

                continue;
            }

            $existing = Sale::where('store_id', $storeId)
                ->where('date_sales', $row->movement_date)
                ->where(function ($q) use ($employeeId) {
                    if ($employeeId) {
                        $q->where('employee_id', $employeeId);
                    } else {
                        $q->whereNull('employee_id');
                    }
                })
                ->first();

            $data = [
                'total_sales' => round((float) $row->total_sales, 2),
                'qtde_total' => (int) $row->qtde_total,
                'source' => 'cigam',
                'user_hash' => md5($row->cpf . $row->store_code . $row->movement_date),
            ];

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                Sale::create(array_merge($data, [
                    'store_id' => $storeId,
                    'employee_id' => $employeeId,
                    'date_sales' => $row->movement_date,
                ]));
                $inserted++;
            }
        }

        Log::info('Sales summary refreshed', compact('inserted', 'updated', 'skipped'));

        return compact('inserted', 'updated', 'skipped');
    }

    // =============================================
    // MOVEMENT TYPES SYNC
    // =============================================

    public function syncMovementTypes(): int
    {
        if (! $this->isAvailable()) {
            return 0;
        }

        $records = DB::connection('cigam')
            ->table('msl_dcodmov_')
            ->select('cod_movimento', 'descricao')
            ->get();

        $count = 0;
        foreach ($records as $record) {
            MovementType::updateOrCreate(
                ['code' => $record->cod_movimento],
                [
                    'description' => trim($record->descricao),
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    // =============================================
    // INTERNAL
    // =============================================

    protected function syncDateRangeInternal(Carbon $start, Carbon $end, MovementSyncLog $log, bool $deleteFirst = true): void
    {
        // Chunk into MAX_CHUNK_DAYS windows
        $chunkStart = $start->copy();

        while ($chunkStart->lte($end)) {
            $chunkEnd = $chunkStart->copy()->addDays(self::MAX_CHUNK_DAYS - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            // Delete existing data for this chunk
            if ($deleteFirst) {
                $deleted = Movement::forDateRange(
                    $chunkStart->toDateString(),
                    $chunkEnd->toDateString()
                )->delete();
                $log->increment('deleted_records', $deleted);
            }

            // Count records in CIGAM for this chunk
            $chunkCount = DB::connection('cigam')
                ->table('msl_fmovimentodiario_')
                ->whereBetween('data', [$chunkStart->toDateString(), $chunkEnd->toDateString()])
                ->count();

            $log->increment('total_records', $chunkCount);

            if ($chunkCount === 0) {
                $chunkStart->addDays(self::MAX_CHUNK_DAYS);

                continue;
            }

            // Stream records using cursor to avoid memory exhaustion
            $cursor = DB::connection('cigam')
                ->table('msl_fmovimentodiario_')
                ->whereBetween('data', [$chunkStart->toDateString(), $chunkEnd->toDateString()])
                ->orderBy('data')
                ->orderBy('hora')
                ->cursor();

            $batchId = (string) Str::uuid();
            $batch = [];

            foreach ($cursor as $record) {
                try {
                    $batch[] = $this->mapCigamRecord($record, $batchId);

                    if (count($batch) >= self::BATCH_INSERT_SIZE) {
                        DB::table('movements')->insert($batch);
                        $log->increment('inserted_records', count($batch));
                        $log->increment('processed_records', count($batch));
                        $batch = [];
                    }
                } catch (\Exception $e) {
                    $log->increment('error_count');
                    $log->increment('skipped_records');
                    Log::warning('Movement record error', [
                        'error' => $e->getMessage(),
                        'date' => $record->data ?? null,
                    ]);
                }
            }

            if (! empty($batch)) {
                DB::table('movements')->insert($batch);
                $log->increment('inserted_records', count($batch));
                $log->increment('processed_records', count($batch));
            }

            $chunkStart->addDays(self::MAX_CHUNK_DAYS);
        }
    }

    protected function mapCigamRecord(object $record, string $batchId): array
    {
        $movementCode = (int) ($record->controle ?? 0);
        $entryExit = strtoupper(trim($record->ent_sai ?? 'S'));
        $realizedValue = (float) ($record->valor_realizado ?? 0);
        $quantity = (float) ($record->qtde ?? 0);

        [$netValue, $netQuantity] = Movement::calculateNetValues(
            $realizedValue, $quantity, $movementCode, $entryExit
        );

        return [
            'movement_date' => $record->data,
            'movement_time' => $record->hora ?: null,
            'store_code' => trim($record->cod_lojas ?? ''),
            'cpf_customer' => trim($record->cpf ?? '') ?: null,
            'invoice_number' => $record->nf ? (string) $record->nf : null,
            'movement_code' => $movementCode,
            'cpf_consultant' => trim($record->cpf_consultora ?? '') ?: null,
            'ref_size' => trim($record->reftam ?? '') ?: null,
            'barcode' => trim($record->cod_barras ?? '') ?: null,
            'sale_price' => (float) ($record->venda ?? 0),
            'cost_price' => (float) ($record->custo ?? 0),
            'realized_value' => $realizedValue,
            'discount_value' => (float) ($record->desconto ?? 0),
            'quantity' => $quantity,
            'entry_exit' => $entryExit,
            'net_value' => $netValue,
            'net_quantity' => $netQuantity,
            'sync_batch_id' => $batchId,
            'synced_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    protected function loadMappings(): void
    {
        if (empty($this->storeCodeMap)) {
            $this->storeCodeMap = Store::pluck('id', 'code')->toArray();
        }
        if (empty($this->employeeCpfMap)) {
            $this->employeeCpfMap = Employee::whereNotNull('cpf')
                ->pluck('id', 'cpf')
                ->toArray();
        }
    }
}
