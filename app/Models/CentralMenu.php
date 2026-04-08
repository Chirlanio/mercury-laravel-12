<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralMenu extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'order',
        'is_active',
        'parent_id',
        'type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public function pageDefaults()
    {
        return $this->hasMany(CentralMenuPageDefault::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeParentMenus($query)
    {
        return $query->whereNull('parent_id');
    }
}
