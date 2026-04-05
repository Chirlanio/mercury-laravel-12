<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = ['bank_name', 'cod_bank', 'is_active'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
