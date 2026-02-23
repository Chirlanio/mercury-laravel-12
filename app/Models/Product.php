<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable;

    protected $fillable = [
        'reference',
        'description',
        'brand_cigam_code',
        'collection_cigam_code',
        'subcollection_cigam_code',
        'category_cigam_code',
        'color_cigam_code',
        'material_cigam_code',
        'article_complement_cigam_code',
        'supplier_codigo_for',
        'sale_price',
        'cost_price',
        'is_active',
        'sync_locked',
        'synced_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sync_locked' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_cigam_code', 'cigam_code');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ProductCollection::class, 'collection_cigam_code', 'cigam_code');
    }

    public function subcollection(): BelongsTo
    {
        return $this->belongsTo(ProductSubcollection::class, 'subcollection_cigam_code', 'cigam_code');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_cigam_code', 'cigam_code');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'color_cigam_code', 'cigam_code');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(ProductMaterial::class, 'material_cigam_code', 'cigam_code');
    }

    public function articleComplement(): BelongsTo
    {
        return $this->belongsTo(ProductArticleComplement::class, 'article_complement_cigam_code', 'cigam_code');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_codigo_for', 'codigo_for');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
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

    public function scopeSyncLocked(Builder $query): Builder
    {
        return $query->where('sync_locked', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('reference', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function getFormattedSalePriceAttribute(): string
    {
        return $this->sale_price ? 'R$ ' . number_format((float) $this->sale_price, 2, ',', '.') : '-';
    }

    public function getFormattedCostPriceAttribute(): string
    {
        return $this->cost_price ? 'R$ ' . number_format((float) $this->cost_price, 2, ',', '.') : '-';
    }
}
