<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralRole extends Model
{
    protected $fillable = [
        'name',
        'label',
        'hierarchy_level',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'hierarchy_level' => 'integer',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function permissions()
    {
        return $this->belongsToMany(
            CentralPermission::class,
            'central_role_permissions',
            'central_role_id',
            'central_permission_id'
        );
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('hierarchy_level');
    }
}
