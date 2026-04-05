<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_users',
        'max_stores',
        'max_storage_mb',
        'price_monthly',
        'price_yearly',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'json',
        'is_active' => 'boolean',
        'max_users' => 'integer',
        'max_stores' => 'integer',
        'max_storage_mb' => 'integer',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
    ];

    public function tenants()
    {
        return $this->hasMany(Tenant::class, 'plan_id');
    }

    public function modules()
    {
        return $this->hasMany(TenantModule::class, 'plan_id');
    }

    public function enabledModules()
    {
        return $this->modules()->where('is_enabled', true);
    }
}
