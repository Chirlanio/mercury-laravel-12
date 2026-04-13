<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Canned reply template (macro) for technicians. Placeholders in the
 * body are resolved on the frontend at paste time so the technician can
 * still edit the resulting text before posting the comment — the
 * server never blindly substitutes and sends.
 */
class HdReplyTemplate extends Model
{
    protected $fillable = [
        'name', 'category', 'body', 'department_id', 'author_id',
        'is_shared', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'usage_count' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Visible templates for the given user. Includes:
     *   - All shared templates
     *   - Personal templates where the user is the author
     *
     * Department filter is additive: if provided, only templates matching
     * the department OR with no department (global) are returned.
     */
    public function scopeVisibleTo(Builder $query, int $userId, ?int $departmentId = null): Builder
    {
        return $query->active()
            ->where(function ($q) use ($userId) {
                $q->where('is_shared', true)->orWhere('author_id', $userId);
            })
            ->when($departmentId, fn ($q) => $q->where(function ($q2) use ($departmentId) {
                $q2->whereNull('department_id')->orWhere('department_id', $departmentId);
            }))
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
