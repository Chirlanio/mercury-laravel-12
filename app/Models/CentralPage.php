<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralPage extends Model
{
    protected $fillable = [
        'page_name',
        'route',
        'controller',
        'method',
        'menu_controller',
        'menu_method',
        'icon',
        'notes',
        'is_public',
        'is_active',
        'central_page_group_id',
        'central_module_id',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function pageGroup()
    {
        return $this->belongsTo(CentralPageGroup::class, 'central_page_group_id');
    }

    public function module()
    {
        return $this->belongsTo(CentralModule::class, 'central_module_id');
    }

    public function menuDefaults()
    {
        return $this->hasMany(CentralMenuPageDefault::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
