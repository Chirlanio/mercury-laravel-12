<?php

namespace App\Models;

use App\Enums\TurnListAttendanceStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pausa de consultora (Intervalo, Almoço).
 *
 * `original_queue_position` é NOT NULL — pausas só ocorrem a partir da
 * fila. O retorno à posição original é controlado por
 * `turn_list_store_settings.return_to_position` (toggle por loja),
 * NÃO pelo break_type.
 *
 * Compara `elapsed_seconds` com `break_type.max_duration_minutes` em
 * runtime — se exceder, painel destaca em vermelho.
 *
 * Reusa o enum TurnListAttendanceStatus (active/finished).
 */
class TurnListBreak extends Model
{
    use Auditable;

    protected $table = 'turn_list_breaks';

    protected $attributes = [
        'status' => 'active',
    ];

    protected $fillable = [
        'employee_id',
        'store_code',
        'break_type_id',
        'original_queue_position',
        'status',
        'started_at',
        'finished_at',
        'duration_seconds',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'status' => TurnListAttendanceStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'integer',
        'original_queue_position' => 'integer',
        'break_type_id' => 'integer',
    ];

    public function getElapsedSecondsAttribute(): int
    {
        if (! $this->started_at) {
            return 0;
        }

        $end = $this->finished_at ?? now();

        return (int) $this->started_at->diffInSeconds($end);
    }

    public function getElapsedMinutesAttribute(): int
    {
        return (int) floor($this->elapsed_seconds / 60);
    }

    /**
     * Retorna true se a pausa excedeu o tempo máximo do break_type.
     * Avaliado em runtime — depende da relação `breakType` carregada.
     */
    public function getIsExceededAttribute(): bool
    {
        $maxMinutes = $this->breakType?->max_duration_minutes;
        if (! $maxMinutes) {
            return false;
        }

        return $this->elapsed_minutes > $maxMinutes;
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_code', 'code');
    }

    public function breakType(): BelongsTo
    {
        return $this->belongsTo(TurnListBreakType::class, 'break_type_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
