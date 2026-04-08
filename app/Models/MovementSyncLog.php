<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementSyncLog extends Model
{
    protected $fillable = [
        'sync_type', 'status', 'total_records', 'processed_records',
        'inserted_records', 'deleted_records', 'skipped_records',
        'error_count', 'error_details', 'date_range_start', 'date_range_end',
        'started_at', 'completed_at', 'started_by_user_id',
    ];

    protected $casts = [
        'error_details' => 'json',
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
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_details' => ['message' => $error],
            'error_count' => $this->error_count + 1,
        ]);
    }
}
