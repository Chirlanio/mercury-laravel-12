<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSyncLog extends Model
{
    protected $fillable = [
        'sync_type',
        'status',
        'current_phase',
        'total_records',
        'processed_records',
        'inserted_records',
        'updated_records',
        'skipped_records',
        'error_count',
        'error_details',
        'started_at',
        'completed_at',
        'started_by_user_id',
    ];

    protected $casts = [
        'error_details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'inserted_records' => 'integer',
        'updated_records' => 'integer',
        'skipped_records' => 'integer',
        'error_count' => 'integer',
    ];

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function markRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error = null): void
    {
        $errors = $this->error_details ?? [];
        if ($error) {
            $errors[] = $error;
        }

        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_details' => $errors,
            'error_count' => count($errors),
        ]);
    }

    public function markCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    public function incrementProcessed(int $inserted = 0, int $updated = 0, int $skipped = 0): void
    {
        $this->increment('processed_records', $inserted + $updated + $skipped);
        if ($inserted) $this->increment('inserted_records', $inserted);
        if ($updated) $this->increment('updated_records', $updated);
        if ($skipped) $this->increment('skipped_records', $skipped);
    }
}
