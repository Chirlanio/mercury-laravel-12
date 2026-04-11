<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelMovementFile extends Model
{
    protected $fillable = [
        'personnel_movement_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by_user_id',
    ];

    public function personnelMovement(): BelongsTo
    {
        return $this->belongsTo(PersonnelMovement::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
