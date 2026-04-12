<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HdDepartment extends Model
{
    use Auditable;

    protected $fillable = ['name', 'description', 'icon', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(HdCategory::class, 'department_id');
    }

    public function activeCategories(): HasMany
    {
        return $this->hasMany(HdCategory::class, 'department_id')->where('is_active', true);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HdTicket::class, 'department_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(HdPermission::class, 'department_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
