<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationLog extends Model
{
    protected $fillable = [
        'vacation_id',
        'action_type',
        'old_status',
        'new_status',
        'changed_by_user_id',
        'notes',
    ];

    // Action types
    public const ACTION_CREATED = 'CREATED';

    public const ACTION_SUBMITTED = 'SUBMITTED';

    public const ACTION_MANAGER_APPROVED = 'MANAGER_APPROVED';

    public const ACTION_HR_APPROVED = 'HR_APPROVED';

    public const ACTION_STARTED = 'STARTED';

    public const ACTION_FINISHED = 'FINISHED';

    public const ACTION_CANCELLED = 'CANCELLED';

    public const ACTION_MANAGER_REJECTED = 'MANAGER_REJECTED';

    public const ACTION_HR_REJECTED = 'HR_REJECTED';

    public const ACTION_RETROACTIVE_CREATED = 'RETROACTIVE_CREATED';

    public const ACTION_ACKNOWLEDGED = 'ACKNOWLEDGED';

    public function vacation(): BelongsTo
    {
        return $this->belongsTo(Vacation::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function getOldStatusLabelAttribute(): ?string
    {
        if (! $this->old_status) {
            return null;
        }

        return Vacation::STATUS_LABELS[$this->old_status] ?? $this->old_status;
    }

    public function getNewStatusLabelAttribute(): string
    {
        return Vacation::STATUS_LABELS[$this->new_status] ?? $this->new_status;
    }
}
