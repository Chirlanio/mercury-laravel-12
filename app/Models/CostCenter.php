<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $fillable = ['code', 'name', 'area_id', 'manager_id', 'is_active'];

    public function manager()
    {
        return $this->belongsTo(Manager::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
