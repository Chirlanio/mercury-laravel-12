<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantModule extends Model
{
    protected $fillable = [
        'plan_id',
        'module_slug',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(TenantPlan::class, 'plan_id');
    }
}
