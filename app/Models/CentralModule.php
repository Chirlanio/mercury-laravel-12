<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralModule extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'routes',
        'dependencies',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'routes' => 'json',
        'dependencies' => 'json',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if any plan uses this module.
     */
    public function isUsedByPlans(): bool
    {
        return TenantModule::where('module_slug', $this->slug)->exists();
    }
}
