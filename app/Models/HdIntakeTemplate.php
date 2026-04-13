<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reusable field schema for ticket intake. Can be attached to a department
 * and/or category. When present, the intake layer renders these fields in
 * addition to the standard title/description so operators capture structured
 * data (e.g. vacation_start, vacation_end, type).
 */
class HdIntakeTemplate extends Model
{
    protected $fillable = [
        'department_id', 'category_id', 'name', 'fields', 'active', 'sort_order',
    ];

    protected $casts = [
        'fields' => 'array',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HdCategory::class, 'category_id');
    }

    public function intakeData(): HasMany
    {
        return $this->hasMany(HdTicketIntakeData::class, 'template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Resolve the first applicable template for a given department/category,
     * preferring the most specific match (category wins over department alone).
     */
    public static function resolveFor(?int $departmentId, ?int $categoryId): ?self
    {
        if ($categoryId) {
            $byCategory = static::active()
                ->where('category_id', $categoryId)
                ->orderBy('sort_order')
                ->first();
            if ($byCategory) {
                return $byCategory;
            }
        }

        if ($departmentId) {
            return static::active()
                ->where('department_id', $departmentId)
                ->whereNull('category_id')
                ->orderBy('sort_order')
                ->first();
        }

        return null;
    }
}
