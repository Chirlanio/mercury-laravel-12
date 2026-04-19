<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Centro de custo. Cadastro de fundação para Orçamentos (Budgets),
 * Ordens de Pagamento (OrderPayment) e futuro DRE.
 *
 * Hierarquia auto-referencial via parent_id permite drill-down no DRE
 * (Administrativo total → TI → Desenvolvimento). Validação de ciclos
 * fica em CostCenterService.
 *
 * Soft delete segue convenção de Reversal/Return/Vacancy/PurchaseOrder:
 * coluna deleted_at manipulada pelo service, sem trait SoftDeletes —
 * MySQL não suporta unique parcial com NULL, então o padrão foi manual.
 */
class CostCenter extends Model
{
    use Auditable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'area_id',
        'parent_id',
        'default_accounting_class_id',
        'manager_id',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

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

    public function scopeByArea(Builder $query, int $areaId): Builder
    {
        return $query->where('area_id', $areaId);
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

    /**
     * Retorna todos os ancestrais (para validação de ciclo + breadcrumb).
     *
     * @return array<int, int>  Lista de IDs ancestrais, do pai imediato ao raiz.
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

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
