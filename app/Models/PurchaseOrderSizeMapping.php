<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapeamento entre labels de tamanho da planilha de importação e
 * product_sizes oficiais (CIGAM).
 *
 * source_label é normalizado (trim + upper) no accessor/mutator pra
 * garantir lookup case-insensitive.
 */
class PurchaseOrderSizeMapping extends Model
{
    use Auditable;

    protected $fillable = [
        'source_label',
        'product_size_id',
        'is_active',
        'auto_detected',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_detected' => 'boolean',
    ];

    // ------------------------------------------------------------------
    // Mutators / accessors — normaliza source_label pra upper trim
    // ------------------------------------------------------------------

    public function setSourceLabelAttribute(?string $value): void
    {
        $this->attributes['source_label'] = $value !== null
            ? mb_strtoupper(trim($value))
            : null;
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('product_size_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('product_size_id');
    }

    // ------------------------------------------------------------------
    // Static helpers
    // ------------------------------------------------------------------

    /**
     * Normaliza um label pra comparação com source_label armazenado.
     * Usado pelo SizeMappingService.
     */
    public static function normalizeLabel(?string $label): string
    {
        if ($label === null) {
            return '';
        }
        return mb_strtoupper(trim($label));
    }
}
