<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = ['name', 'cnh', 'cnh_category', 'phone', 'is_active'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
