<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alias entre nome de marca da planilha de importação e product_brands
 * oficial (CIGAM).
 *
 * source_name é normalizado (upper + trim) no mutator pra garantir
 * lookup case-insensitive.
 */
class PurchaseOrderBrandAlias extends Model
{
    use Auditable;

    protected $fillable = [
        'source_name',
        'product_brand_id',
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

    public function setSourceNameAttribute(?string $value): void
    {
        $this->attributes['source_name'] = $value !== null
            ? self::normalizeName($value)
            : null;
    }

    public function productBrand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('product_brand_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('product_brand_id');
    }

    /**
     * Normaliza nome pra comparação — upper, trim, sem acentos, sem
     * múltiplos espaços consecutivos. Mais tolerante que product_sizes
     * (que mantém "/" e "."): marcas podem ter espaços variáveis.
     */
    public static function normalizeName(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $name = trim($name);
        if ($name === '') {
            return '';
        }

        // Remove acentos
        $noAccents = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($noAccents === false || $noAccents === '') {
            $noAccents = $name;
        }

        // Limpa caracteres de transliteração ruins
        $cleaned = preg_replace('/["\'`^~]/', '', $noAccents);
        // Colapsa múltiplos espaços em um
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        return mb_strtoupper($cleaned);
    }
}
