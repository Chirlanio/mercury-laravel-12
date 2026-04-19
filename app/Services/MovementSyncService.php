<?php

namespace App\Services;

use App\Models\Movement;
use App\Models\MovementSyncLog;
use App\Models\MovementType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MovementSyncService
{
    const BATCH_INSERT_SIZE = 500;
    const SALES_UPSERT_CHUNK = 500;
    const MAX_CHUNK_DAYS = 7;
    const MAX_RANGE_DAYS = 180;

    protected CigamSyncService $cigamService;

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
                try {
                    $batch[] = $this->mapCigamRecord($record, $batchId);

                    if (count($batch) >= self::BATCH_INSERT_SIZE) {
                        DB::table('movements')->insert($batch);
                        $log->increment('inserted_records', count($batch));
                        $log->increment('processed_records', count($batch));
                        $batch = [];
                    }
                } catch (\Exception $e) {
                    $log->pushError([
                        'phase' => 'map',
                        'record_date' => $record->data ?? null,
                        'record_time' => $record->hora ?? null,
                        'store' => $record->cod_lojas ?? null,
                        'invoice' => $record->nf ?? null,
                        'barcode' => $record->cod_barras ?? null,
                        'message' => $e->getMessage(),
                    ]);
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
            $log->markFailed("Período máximo de ".self::MAX_RANGE_DAYS.' dias excedido.');

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

    /**
     * Atualiza a tabela sales agregando movements (vendas + devoluções).
     * Usa JOIN para resolver store_id e employee_id direto no SQL (sem carregar maps em memória)
     * e processa em chunks para evitar picos de RAM.
     */
    public function refreshSalesSummary(Carbon $start, Carbon $end): array
    {
        $cpfExpr = "COALESCE(NULLIF(TRIM(m.cpf_consultant), ''), '00000000000')";

        $base = DB::table('movements as m')
            ->leftJoin('stores as s', 's.code', '=', 'm.store_code')
            ->leftJoin('employees as e', function ($join) use ($cpfExpr) {
                $join->on('e.cpf', '=', DB::raw($cpfExpr));
            })
            ->select(
                'm.movement_date',
                'm.store_code',
                DB::raw("$cpfExpr as cpf"),
                's.id as store_id',
                'e.id as employee_id',
                DB::raw("SUM(CASE WHEN m.movement_code = 6 AND m.entry_exit = 'E' THEN -m.realized_value ELSE m.realized_value END) as total_sales"),
                DB::raw("CAST(SUM(CASE WHEN m.movement_code = 6 AND m.entry_exit = 'E' THEN -m.quantity ELSE m.quantity END) AS SIGNED) as qtde_total"),
            )
            ->where(function ($q) {
                $q->where('m.movement_code', 2)
                    ->orWhere(function ($q2) {
                        $q2->where('m.movement_code', 6)->where('m.entry_exit', 'E');
                    });
            })
            ->whereBetween('m.movement_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('m.movement_date', 'm.store_code', DB::raw($cpfExpr), 's.id', 'e.id')
            ->orderBy('m.movement_date')
            ->orderBy('m.store_code');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $base->chunk(self::SALES_UPSERT_CHUNK, function ($rows) use (&$inserted, &$updated, &$skipped) {
            foreach ($rows as $row) {
                if (! $row->store_id) {
                    $skipped++;

                    continue;
                }

                $data = [
                    'total_sales' => round((float) $row->total_sales, 2),
                    'qtde_total' => (int) $row->qtde_total,
                    'source' => 'cigam',
                    'user_hash' => md5($row->cpf.$row->store_code.$row->movement_date),
                    'updated_at' => now(),
                ];

                $affected = DB::table('sales')
                    ->where('store_id', $row->store_id)
                    ->where('date_sales', $row->movement_date)
                    ->when($row->employee_id, fn ($q) => $q->where('employee_id', $row->employee_id))
                    ->when(! $row->employee_id, fn ($q) => $q->whereNull('employee_id'))
                    ->update($data);

                if ($affected > 0) {
                    $updated++;
                } else {
                    DB::table('sales')->insert(array_merge($data, [
                        'store_id' => $row->store_id,
                        'employee_id' => $row->employee_id,
                        'date_sales' => $row->movement_date,
                        'created_at' => now(),
                    ]));
                    $inserted++;
                }
            }
        });

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
        $chunkStart = $start->copy();

        while ($chunkStart->lte($end)) {
            $chunkEnd = $chunkStart->copy()->addDays(self::MAX_CHUNK_DAYS - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            if ($deleteFirst) {
                $this->captureDeletionSummary($log, $chunkStart, $chunkEnd);

                $deleted = Movement::forDateRange(
                    $chunkStart->toDateString(),
                    $chunkEnd->toDateString()
                )->delete();
                $log->increment('deleted_records', $deleted);
            }

            $chunkCount = DB::connection('cigam')
                ->table('msl_fmovimentodiario_')
                ->whereBetween('data', [$chunkStart->toDateString(), $chunkEnd->toDateString()])
                ->count();

            $log->increment('total_records', $chunkCount);

            if ($chunkCount === 0) {
                $chunkStart->addDays(self::MAX_CHUNK_DAYS);

                continue;
            }

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
                    $log->pushError([
                        'phase' => 'map',
                        'record_date' => $record->data ?? null,
                        'record_time' => $record->hora ?? null,
                        'store' => $record->cod_lojas ?? null,
                        'invoice' => $record->nf ?? null,
                        'barcode' => $record->cod_barras ?? null,
                        'message' => $e->getMessage(),
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

    /**
     * Agrega contagem de registros a serem deletados por loja, data e movement_code
     * antes do delete físico, para auditoria.
     */
    protected function captureDeletionSummary(MovementSyncLog $log, Carbon $start, Carbon $end): void
    {
        $rows = DB::table('movements')
            ->selectRaw('store_code, movement_date, movement_code, COUNT(*) as total')
            ->whereBetween('movement_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('store_code', 'movement_date', 'movement_code')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $summary = ['by_store' => [], 'by_date' => [], 'by_movement_code' => [], 'total' => 0];

        foreach ($rows as $row) {
            $count = (int) $row->total;
            $summary['by_store'][$row->store_code] = ($summary['by_store'][$row->store_code] ?? 0) + $count;
            $date = $row->movement_date instanceof \DateTimeInterface
                ? $row->movement_date->format('Y-m-d')
                : (string) $row->movement_date;
            $summary['by_date'][$date] = ($summary['by_date'][$date] ?? 0) + $count;
            $summary['by_movement_code'][$row->movement_code] = ($summary['by_movement_code'][$row->movement_code] ?? 0) + $count;
            $summary['total'] += $count;
        }

        $log->mergeDeletionSummary($summary);
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
}
