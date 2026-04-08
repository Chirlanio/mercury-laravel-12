<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCategory extends Model
{
    protected $table = 'product_categories';

    protected $fillable = [
        'cigam_code',
        'name',
        'is_active',
        'group_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductLookupGroup::class, 'group_id');
    }
}
