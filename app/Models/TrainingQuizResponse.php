<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingQuizResponse extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attempt_id', 'question_id', 'selected_options',
        'is_correct', 'points_earned',
    ];

    protected $casts = [
        'selected_options' => 'array',
        'is_correct' => 'boolean',
        'points_earned' => 'integer',
    ];

    // Relationships

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrainingQuizAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TrainingQuizQuestion::class, 'question_id');
    }
}
