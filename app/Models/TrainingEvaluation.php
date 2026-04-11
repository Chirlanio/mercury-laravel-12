<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEvaluation extends Model
{
    protected $fillable = [
        'training_id', 'participant_id',
        'rating', 'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    // Relationships

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(TrainingParticipant::class, 'participant_id');
    }
}
