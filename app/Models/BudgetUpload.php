<?php

namespace App\Models;

use App\Enums\BudgetUploadType;
use App\Traits\Auditable;
use App\Traits\InvalidatesDreCacheOnChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header de um upload de orçamento — uma versão ativa por (year, scope_label).
 *
 * Versionamento (paridade v1):
 *   - Primeiro upload do scope/year → 1.0
 *   - Novo upload same year + type=novo → incrementa major (1.0 → 2.0)
 *   - Novo upload same year + type=ajuste → incrementa minor (1.0 → 1.01)
 *   - Year diferente → reset (1.0)
 *
 * Regras em BudgetVersionService.
 *
 * Soft delete manual + audit seguindo o padrão Reversal/Return/PurchaseOrder.
 */
class BudgetUpload extends Model
{
    use Auditable, InvalidatesDreCacheOnChange;

    protected $fillable = [
        'year',
        'scope_label',
        'area_department_id',
        'version_label',
        'major_version',
        'minor_version',
        'upload_type',
        'original_filename',
        'stored_path',
        'file_size_bytes',
        'is_active',
        'notes',
        'total_year',
        'items_count',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'upload_type' => BudgetUploadType::class,
        'year' => 'integer',
        'major_version' => 'integer',
        'minor_version' => 'integer',
        'file_size_bytes' => 'integer',
        'is_active' => 'boolean',
        'total_year' => 'decimal:2',
        'items_count' => 'integer',
        'deleted_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    /**
     * Departamento gerencial (sintético 8.1.DD) que representa a "Área" do
     * orçamento — Marketing, Operações, Fiscal, Comercial, etc. Opcional
     * em uploads legacy; obrigatório para uploads novos (validado no
     * controller, não no banco).
     */
    public function areaDepartment(): BelongsTo
    {
        return $this->belongsTo(ManagementClass::class, 'area_department_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BudgetStatusHistory::class)->orderBy('created_at');
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

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function scopeForScope(Builder $query, string $scopeLabel): Builder
    {
        return $query->where('scope_label', $scopeLabel);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('scope_label', 'like', $like)
                ->orWhere('version_label', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhere('original_filename', 'like', $like);
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Compila "1.0", "1.01", "2.05" a partir de major+minor.
     */
    public static function composeVersionLabel(int $major, int $minor): string
    {
        if ($minor === 0) {
            return "{$major}.0";
        }

        // Padronização v1: minor sempre 2 dígitos ("1.01", "1.12")
        return sprintf('%d.%02d', $major, $minor);
    }
}
