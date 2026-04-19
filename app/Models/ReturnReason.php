<?php

namespace App\Models;

use App\Enums\ReturnReasonCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'category' => ReturnReasonCategory::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory(Builder $query, ReturnReasonCategory|string $category): Builder
    {
        return $query->where(
            'category',
            $category instanceof ReturnReasonCategory ? $category->value : $category
        );
    }
}
