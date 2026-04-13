<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HdBusinessHour extends Model
{
    protected $fillable = ['department_id', 'weekday', 'start_time', 'end_time'];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }
}
