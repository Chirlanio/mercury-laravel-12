<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\InvalidatesDreCacheOnChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fechamento de período da DRE.
 *
 * `closed_up_to_date` é inclusivo (fechar janeiro/2026 grava `2026-01-31`).
 * Ao fechar, `DrePeriodClosingService::close()` gera snapshots em
 * `dre_period_closing_snapshots` e a partir daí a matriz passa a ler dos
 * snapshots para esse período (imutabilidade).
 *
 * Reabertura preenche `reopened_by_user_id` + `reopened_at` + `reopen_reason`.
 * Um fechamento "ativo" é aquele com `reopened_at IS NULL`. Uma mesma data
 * pode ter vários fechamentos ao longo do tempo (fechou → reabriu →
 * refechou); a regra é "só 1 ativo por data", enforced pelo service.
 */
class DrePeriodClosing extends Model
{
    use Auditable, HasFactory, InvalidatesDreCacheOnChange;

    protected $table = 'dre_period_closings';

    protected $fillable = [
        'closed_up_to_date',
        'closed_by_user_id',
        'closed_at',
        'reopened_by_user_id',
        'reopened_at',
        'reopen_reason',
        'notes',
    ];

    protected $casts = [
        'closed_up_to_date' => 'date:Y-m-d',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function snapshots(): HasMany
    {
        return $this->hasMany(DrePeriodClosingSnapshot::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('reopened_at');
    }

    public function scopeReopened(Builder $query): Builder
    {
        return $query->whereNotNull('reopened_at');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->reopened_at === null;
    }

    /**
     * Último `closed_up_to_date` ativo (não-reaberto) ou null se nada fechado.
     *
     * Usado por projetores (marcar `reported_in_closed_period`) e importadores
     * (rejeitar linhas com `entry_date` já coberta por fechamento ativo).
     */
    public static function lastClosedUpTo(): ?string
    {
        $value = static::query()
            ->active()
            ->orderByDesc('closed_up_to_date')
            ->value('closed_up_to_date');

        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : (string) $value;
    }
}
