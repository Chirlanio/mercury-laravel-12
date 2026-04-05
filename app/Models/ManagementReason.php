<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagementReason extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
