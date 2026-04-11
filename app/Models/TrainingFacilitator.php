<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingFacilitator extends Model
{
    use Auditable;

    protected $fillable = [
        'name', 'email', 'phone', 'bio', 'photo_path',
        'external', 'employee_id', 'is_active',
        'created_by_user_id', 'updated_by_user_id',
        'deleted_at', 'deleted_by_user_id',
    ];

    protected $casts = [
        'external' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // Relationships

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class, 'facilitator_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('external', false);
    }

    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('external', true);
    }

    // Accessors

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    public function getDisplayNameAttribute(): string
    {
        $suffix = $this->external ? ' (Externo)' : '';

        return $this->name.$suffix;
    }
}
