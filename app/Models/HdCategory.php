<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HdCategory extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['department_id', 'name', 'description', 'is_active', 'default_priority'];

    protected $casts = [
        'is_active' => 'boolean',
        'default_priority' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HdTicket::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }
}
