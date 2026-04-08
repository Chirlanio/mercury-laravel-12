<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralPermission extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'description',
        'group',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(
            CentralRole::class,
            'central_role_permissions',
            'central_permission_id',
            'central_role_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGrouped($query)
    {
        return $query->orderBy('group')->orderBy('slug');
    }
}
