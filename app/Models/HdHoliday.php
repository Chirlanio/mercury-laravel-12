<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HdHoliday extends Model
{
    protected $fillable = ['department_id', 'date', 'description'];

    protected $casts = [
        'date' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }
}
