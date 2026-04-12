<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HdPermission extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['user_id', 'department_id', 'level'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }

    public function isTechnician(): bool
    {
        return $this->level === 'technician';
    }

    public function isManager(): bool
    {
        return $this->level === 'manager';
    }
}
