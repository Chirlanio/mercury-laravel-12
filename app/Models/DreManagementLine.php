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
 * Linha da DRE gerencial (apresentação executiva).
 *
 * Estrutura única vigente — sem versionamento paralelo. Evolução via
 * `effective_from`/`effective_to` em `dre_mappings` + snapshot em
 * `dre_period_closing_snapshots` para imutabilidade de períodos fechados.
 *
 * `sort_order` em passos de 10 permite inserção futura sem renumerar.
 * `accumulate_until_sort_order` (só para subtotais) permite acumular
 * não-encadeado — ex: EBITDA soma 1..13 sem carregar Impostos da linha 15.
 *
 * Soft delete manual.
 */
class DreManagementLine extends Model
{
    use Auditable, HasFactory, InvalidatesDreCacheOnChange;

    protected $table = 'dre_management_lines';

    public const NATURE_REVENUE = 'revenue';
    public const NATURE_EXPENSE = 'expense';
    public const NATURE_SUBTOTAL = 'subtotal';

    public const UNCLASSIFIED_CODE = 'L99_UNCLASSIFIED';

    protected $fillable = [
        'code',
        'sort_order',
        'is_subtotal',
        'accumulate_until_sort_order',
        'level_1',
        'level_2',
        'level_3',
        'level_4',
        'nature',
        'is_active',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'is_subtotal' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'accumulate_until_sort_order' => 'integer',
        'deleted_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function mappings(): HasMany
    {
        return $this->hasMany(DreMapping::class);
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeSubtotals(Builder $query): Builder
    {
        return $query->where('is_subtotal', true);
    }

    public function scopeNotSubtotals(Builder $query): Builder
    {
        return $query->where('is_subtotal', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isUnclassified(): bool
    {
        return $this->code === self::UNCLASSIFIED_CODE;
    }
}
