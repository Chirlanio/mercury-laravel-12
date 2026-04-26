<?php

namespace App\Models;

use App\Enums\TurnListAttendanceStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atendimento na Lista da Vez.
 *
 * `original_queue_position` é capturada no `start()` antes de remover
 * a consultora da fila. Quando o outcome de finalização tiver
 * `restore_queue_position=1`, a consultora volta na posição original
 * AJUSTADA pelo algoritmo do Service:
 *
 *   adjustedPosition = max(1, original_queue_position - aheadCount)
 *
 *   onde aheadCount = quantas consultoras estavam à FRENTE na fila E
 *   também saíram pra atender DEPOIS desta (e ainda estão atendendo).
 *
 * Atendimentos finalizados são imutáveis (auditoria/relatórios).
 */
class TurnListAttendance extends Model
{
    use Auditable;
    use HasUlids;

    protected $table = 'turn_list_attendances';

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected $attributes = [
        'status' => 'active',
    ];

    protected $fillable = [
        'ulid',
        'employee_id',
        'store_code',
        'original_queue_position',
        'status',
        'started_at',
        'finished_at',
        'duration_seconds',
        'outcome_id',
        'return_to_queue',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'status' => TurnListAttendanceStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'integer',
        'original_queue_position' => 'integer',
        'return_to_queue' => 'boolean',
    ];

    /**
     * Segundos transcorridos desde started_at (até finished_at, se houver,
     * ou até agora). Útil pro timer realtime no painel.
     */
    public function getElapsedSecondsAttribute(): int
    {
        if (! $this->started_at) {
            return 0;
        }

        $end = $this->finished_at ?? now();

        return (int) $this->started_at->diffInSeconds($end);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === TurnListAttendanceStatus::ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TurnListAttendanceStatus::ACTIVE->value);
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->where('status', TurnListAttendanceStatus::FINISHED->value);
    }

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_code', $storeCode);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Atendimentos do dia atual (usado no cleanup e nos relatórios diários).
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('started_at', now()->toDateString());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_code', 'code');
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(TurnListAttendanceOutcome::class, 'outcome_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
