<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovementType extends Model
{
    protected $fillable = [
        'code',
        'description',
        'synced_at',
    ];

    protected $casts = [
        'code' => 'integer',
        'synced_at' => 'datetime',
    ];
}
