<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\InvalidatesDreCacheOnChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De-para entre (conta contábil analítica + centro de custo opcional)
 * e a linha da DRE gerencial.
 *
 * Precedência aplicada pelo `DreMappingResolver` (prompt #6):
 * mapping específico com `cost_center_id` bate antes do coringa com
 * `cost_center_id=NULL`.
 *
 * Vigência via `effective_from` (obrigatório) e `effective_to` (null = vigente).
 * Mudanças retroativas em períodos fechados são bloqueadas pelo
 * `DreMappingService` (§2.4 + §2.8 do plano arquitetural).
 *
 * Soft delete manual.
 */
class DreMapping extends Model
{
    use Auditable, HasFactory, InvalidatesDreCacheOnChange;

    protected $table = 'dre_mappings';

    protected $fillable = [
        'chart_of_account_id',
        'cost_center_id',
        'dre_management_line_id',
        'effective_from',
        'effective_to',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'effective_from' => 'date:Y-m-d',
        'effective_to' => 'date:Y-m-d',
        'deleted_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function dreManagementLine(): BelongsTo
    {
        return $this->belongsTo(DreManagementLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->whereNull('deleted_at')
            ->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Versão que aceita Carbon (ou qualquer DateTimeInterface). Pedido
     * pelo prompt #2. Normaliza para string 'Y-m-d' e delega.
     */
    public function scopeEffectiveAt(Builder $query, string|\DateTimeInterface $date): Builder
    {
        $dateStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        return $this->scopeEffectiveOn($query, $dateStr);
    }

    public function scopeEffectiveBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereNull('deleted_at')
            ->where('effective_from', '<=', $to)
            ->where(function (Builder $q) use ($from) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
            });
    }

    public function scopeForChartOfAccount(Builder $query, int $chartOfAccountId): Builder
    {
        return $query->where('chart_of_account_id', $chartOfAccountId);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isWildcard(): bool
    {
        return $this->cost_center_id === null;
    }

    public function isActiveOn(string $date): bool
    {
        if ($this->deleted_at !== null) {
            return false;
        }

        $from = (string) $this->effective_from;
        if ($from && $from > $date) {
            return false;
        }

        $to = (string) $this->effective_to;
        if ($to && $to < $date) {
            return false;
        }

        return true;
    }
}
