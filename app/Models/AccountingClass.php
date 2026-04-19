<?php

namespace App\Models;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta contábil do Plano de Contas (base do DRE + orçamentos).
 *
 * Hierarquia:
 *   - Grupos sintéticos (accepts_entries=false) — totalizam filhos, não
 *     recebem lançamento direto. Ex: "Despesas Comerciais", "3.1.01".
 *   - Contas analíticas (accepts_entries=true) — folhas, recebem os
 *     lançamentos reais. Ex: "Salários", "Aluguel".
 *
 * Cada conta pertence a exatamente um `DreGroup`, determinando onde
 * aparece no DRE. `nature` é livre — tipicamente segue a natureza natural
 * do grupo, mas pode divergir em casos específicos.
 *
 * Soft delete manual sem trait SoftDeletes (padrão Reversal/Return/CostCenter).
 */
class AccountingClass extends Model
{
    use Auditable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'nature',
        'dre_group',
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
        'nature' => AccountingNature::class,
        'dre_group' => DreGroup::class,
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

    public function scopeByDreGroup(Builder $query, DreGroup|string $group): Builder
    {
        $value = $group instanceof DreGroup ? $group->value : $group;

        return $query->where('dre_group', $value);
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

    /**
     * Retorna IDs dos ancestrais (do pai imediato até a raiz).
     *
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

    /**
     * Verifica se a natureza da conta segue a natureza natural do grupo DRE.
     * Útil para sinalizar divergências na UI.
     */
    public function followsNaturalNature(): bool
    {
        if (! $this->nature || ! $this->dre_group) {
            return true;
        }

        return $this->nature === $this->dre_group->naturalNature();
    }
}
