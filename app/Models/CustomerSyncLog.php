<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de execuções do customers:sync — análogo ao
 * ProductSyncLog, mas sem as fases internas porque a view
 * msl_dcliente_ é um único SELECT sem lookups dependentes.
 */
class CustomerSyncLog extends Model
{
    protected $table = 'customer_sync_logs';

    protected $fillable = [
        'sync_type',
        'status',
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
    ];

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
