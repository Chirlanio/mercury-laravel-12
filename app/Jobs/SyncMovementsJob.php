<?php

namespace App\Jobs;

use App\Models\MovementSyncLog;
use App\Services\MovementSyncService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMovementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes for large syncs

    public int $tries = 1;

    public function __construct(
        public int $logId,
        public string $mode,
        public ?int $userId = null,
        public ?int $month = null,
        public ?int $year = null,
        public ?string $dateStart = null,
        public ?string $dateEnd = null,
    ) {}

    public function handle(MovementSyncService $service): void
    {
        $log = MovementSyncLog::find($this->logId);

        if (! $log) {
            Log::error('SyncMovementsJob: log not found', ['logId' => $this->logId]);

            return;
        }

        match ($this->mode) {
            'auto' => $service->syncAutomatic($this->userId, $log),
            'today' => $service->syncToday($this->userId, $log),
            'month' => $service->syncByMonth($this->month, $this->year, $this->userId, $log),
            'range' => $service->syncByDateRange(
                Carbon::parse($this->dateStart),
                Carbon::parse($this->dateEnd),
                $this->userId,
                $log,
            ),
            'types' => $this->syncTypes($service, $log),
        };
    }

    protected function syncTypes(MovementSyncService $service, MovementSyncLog $log): void
    {
        try {
            $count = $service->syncMovementTypes();
            $log->update([
                'total_records' => $count,
                'processed_records' => $count,
                'inserted_records' => $count,
            ]);
            $log->markCompleted();
        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('SyncMovementsJob types failed', ['error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $log = MovementSyncLog::find($this->logId);
        if ($log && $log->status === 'running') {
            $log->markFailed('Job falhou: ' . $exception->getMessage());
        }

        Log::error('SyncMovementsJob failed', [
            'mode' => $this->mode,
            'error' => $exception->getMessage(),
        ]);
    }
}
