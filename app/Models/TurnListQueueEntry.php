<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada na fila de espera da Lista da Vez.
 *
 * Cada (employee_id, store_code) só pode ter UMA entrada simultânea
 * (unique key). Validações de "não está atendendo" / "não está em pausa"
 * ficam no Service, não no Model.
 *
 * `position` é local à loja (1..N por store_code), não global.
 */
class TurnListQueueEntry extends Model
{
    use Auditable;

    protected $table = 'turn_list_waiting_queue';

    protected $fillable = [
        'employee_id',
        'store_code',
        'position',
        'entered_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'position' => 'integer',
        'entered_at' => 'datetime',
    ];

    /**
     * Tempo de espera em segundos (desde entered_at).
     */
    public function getWaitingSecondsAttribute(): int
    {
        return $this->entered_at
            ? (int) $this->entered_at->diffInSeconds(now())
            : 0;
    }

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_code', $storeCode);
    }

    public function scopeOrderedByPosition(Builder $query): Builder
    {
        return $query->orderBy('position', 'asc');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_code', 'code');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
