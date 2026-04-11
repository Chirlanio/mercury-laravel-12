<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelMovementStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'personnel_movement_status_history';

    protected $fillable = [
        'personnel_movement_id',
        'old_status',
        'new_status',
        'changed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function personnelMovement(): BelongsTo
    {
        return $this->belongsTo(PersonnelMovement::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function getOldStatusLabelAttribute(): ?string
    {
        return PersonnelMovement::STATUS_LABELS[$this->old_status] ?? $this->old_status;
    }

    public function getNewStatusLabelAttribute(): string
    {
        return PersonnelMovement::STATUS_LABELS[$this->new_status] ?? $this->new_status;
    }
}
