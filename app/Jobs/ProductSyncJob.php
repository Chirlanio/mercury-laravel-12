<?php

namespace App\Jobs;

use App\Models\ProductSyncLog;
use App\Services\ProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProductSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public int $logId,
        public string $syncType,
    ) {}

    public function handle(ProductSyncService $service): void
    {
        $log = ProductSyncLog::findOrFail($this->logId);

        try {
            // Phase 1: Lookups (unless prices_only)
            if ($this->syncType !== 'prices_only') {
                $log->update(['current_phase' => 'lookups']);
                $service->syncLookups();
            }

            if ($this->isCancelled($log)) return;

            // Phase 2: Product chunks with keyset pagination
            if (!in_array($this->syncType, ['prices_only', 'lookups_only'])) {
                $log->update(['current_phase' => 'products']);
                $lastReference = null;
                $hasMore = true;

                while ($hasMore) {
                    if ($this->isCancelled($log)) return;

                    $result = $service->processChunk($this->logId, $lastReference, 500);
                    $hasMore = $result['has_more'];
                    $lastReference = $result['last_reference'] ?? null;
                }
            }

            if ($this->isCancelled($log)) return;

            // Phase 3: Prices
            if ($this->syncType !== 'lookups_only') {
                $log->update(['current_phase' => 'prices']);
                $service->syncPrices($this->logId);
            }

            // Phase 4: Finalize
            $service->finalizeSync($this->logId);

        } catch (\Throwable $e) {
            Log::error("ProductSyncJob failed: {$e->getMessage()}", [
                'log_id' => $this->logId,
                'exception' => $e,
            ]);
            $log->markFailed($e->getMessage());
        }
    }

    private function isCancelled(ProductSyncLog $log): bool
    {
        return $log->fresh()->status === 'cancelled';
    }

    public function failed(\Throwable $e): void
    {
        ProductSyncLog::find($this->logId)?->markFailed($e->getMessage());
    }
}
