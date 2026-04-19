<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta do Plano de Contas Gerencial — visão interna operacional do
 * financeiro, complementar ao plano contábil.
 *
 * Diferença em relação a AccountingClass:
 *   - AccountingClass segue normas BR (CPC + Pronunciamentos) para o DRE
 *   - ManagementClass reflete como a empresa INTERNAMENTE organiza
 *     despesas para tomada de decisão (por área, projeto, canal, etc.)
 *
 * Relacionamentos opcionais no MVP:
 *   - accounting_class_id → mapeamento default para DRE automático
 *   - cost_center_id      → CC default para resolver no import de orçamento
 *
 * Sem enums de natureza/DRE group — isso vem herdado via accounting_class
 * quando o vínculo está preenchido.
 */
class ManagementClass extends Model
{
    use Auditable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'accounting_class_id',
        'cost_center_id',
        'accepts_entries',
        'sort_order',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'accepts_entries' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function accountingClass(): BelongsTo
    {
        return $this->belongsTo(AccountingClass::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
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

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeLeaves(Builder $query): Builder
    {
        return $query->where('accepts_entries', true);
    }

    public function scopeSyntheticGroups(Builder $query): Builder
    {
        return $query->where('accepts_entries', false);
    }

    public function scopeLinkedToAccounting(Builder $query): Builder
    {
        return $query->whereNotNull('accounting_class_id');
    }

    public function scopeUnlinkedFromAccounting(Builder $query): Builder
    {
        return $query->whereNull('accounting_class_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isLeaf(): bool
    {
        return (bool) $this->accepts_entries;
    }

    public function isSyntheticGroup(): bool
    {
        return ! $this->accepts_entries;
    }

    public function hasAccountingLink(): bool
    {
        return $this->accounting_class_id !== null;
    }

    /**
     * @return array<int, int>
     */
    public function ancestorsIds(): array
    {
        $ids = [];
        $current = $this->parent;
        while ($current && ! in_array($current->id, $ids, true)) {
            $ids[] = $current->id;
            $current = $current->parent;
        }

        return $ids;
    }
}
