<?php

namespace App\Models;

use App\Traits\InvalidatesDreCacheOnChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Orçado normalizado — 1 linha por mês (entry_date = dia 1 do mês).
 *
 * Alimentado por:
 *   - BudgetToDreProjector (observer em BudgetUpload.is_active flip)
 *   - DreBudgetsImporter (upload manual)
 *   - command dre:import-action-plan (prompt #10.5 — usa Action Plan v1.xlsx)
 *
 * `amount` segue a mesma convenção de sinal de `dre_actuals`.
 *
 * Sem soft delete. Versões coexistem via `budget_version` — mudança de
 * versão = linhas novas, não UPDATE.
 */
class DreBudget extends Model
{
    use HasFactory, InvalidatesDreCacheOnChange;

    protected $table = 'dre_budgets';

    protected $fillable = [
        'entry_date',
        'chart_of_account_id',
        'cost_center_id',
        'store_id',
        'amount',
        'budget_version',
        'budget_upload_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function budgetUpload(): BelongsTo
    {
        return $this->belongsTo(BudgetUpload::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    public function scopeForPeriod(Builder $query, string|\DateTimeInterface $from, string|\DateTimeInterface $to): Builder
    {
        $fromStr = $from instanceof \DateTimeInterface ? $from->format('Y-m-d') : $from;
        $toStr = $to instanceof \DateTimeInterface ? $to->format('Y-m-d') : $to;

        return $query->whereBetween('entry_date', [$fromStr, $toStr]);
    }

    public function scopeForVersion(Builder $query, string $version): Builder
    {
        return $query->where('budget_version', $version);
    }

    public function scopeForStores(Builder $query, array $storeIds): Builder
    {
        return $query->whereIn('store_id', $storeIds);
    }

    public function scopeForUnit(Builder $query, int|array|Store $unit): Builder
    {
        if ($unit instanceof Store) {
            return $query->where('store_id', $unit->id);
        }

        if (is_array($unit)) {
            return $query->whereIn('store_id', $unit);
        }

        return $query->where('store_id', $unit);
    }

    public function scopeForCostCenter(Builder $query, int|CostCenter $costCenter): Builder
    {
        $id = $costCenter instanceof CostCenter ? $costCenter->id : $costCenter;

        return $query->where('cost_center_id', $id);
    }
}
