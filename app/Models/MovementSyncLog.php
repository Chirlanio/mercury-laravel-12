<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementSyncLog extends Model
{
    const MAX_ERROR_RECORDS = 200;

    protected $fillable = [
        'sync_type', 'status', 'total_records', 'processed_records',
        'inserted_records', 'deleted_records', 'skipped_records',
        'error_count', 'error_details', 'deletion_summary',
        'date_range_start', 'date_range_end',
        'started_at', 'completed_at', 'started_by_user_id',
    ];

    protected $casts = [
        'error_details' => 'json',
        'deletion_summary' => 'json',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'inserted_records' => 'integer',
        'deleted_records' => 'integer',
        'skipped_records' => 'integer',
        'error_count' => 'integer',
        'date_range_start' => 'date',
        'date_range_end' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public static function start(string $type, ?int $userId = null): self
    {
        return self::create([
            'sync_type' => $type,
            'status' => 'running',
            'started_at' => now(),
            'started_by_user_id' => $userId,
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $details = $this->error_details ?? [];
        $details['message'] = $error;

        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_details' => $details,
            'error_count' => ($this->error_count ?? 0) + 1,
        ]);
    }

    /**
     * Append a record-level error to error_details.records (capped at MAX_ERROR_RECORDS).
     * Increments error_count and skipped_records on the same model.
     */
    public function pushError(array $error): void
    {
        $details = $this->error_details ?? [];
        $records = $details['records'] ?? [];

        if (count($records) < self::MAX_ERROR_RECORDS) {
            $records[] = array_merge([
                'timestamp' => now()->toIso8601String(),
            ], $error);
        } else {
            $details['truncated'] = ($details['truncated'] ?? 0) + 1;
        }

        $details['records'] = $records;
        $this->error_details = $details;
        $this->error_count = ($this->error_count ?? 0) + 1;
        $this->skipped_records = ($this->skipped_records ?? 0) + 1;
        $this->save();
    }

    /**
     * Merge a deletion summary chunk into deletion_summary JSON (per store/date/code).
     */
    public function mergeDeletionSummary(array $chunk): void
    {
        $summary = $this->deletion_summary ?? ['by_store' => [], 'by_date' => [], 'by_movement_code' => [], 'total' => 0];

        foreach ($chunk['by_store'] ?? [] as $store => $count) {
            $summary['by_store'][$store] = ($summary['by_store'][$store] ?? 0) + $count;
        }
        foreach ($chunk['by_date'] ?? [] as $date => $count) {
            $summary['by_date'][$date] = ($summary['by_date'][$date] ?? 0) + $count;
        }
        foreach ($chunk['by_movement_code'] ?? [] as $code => $count) {
            $summary['by_movement_code'][$code] = ($summary['by_movement_code'][$code] ?? 0) + $count;
        }
        $summary['total'] = ($summary['total'] ?? 0) + ($chunk['total'] ?? 0);

        $this->deletion_summary = $summary;
        $this->save();
    }
}
