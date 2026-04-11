<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingQuizResponse extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attempt_id', 'question_id', 'selected_options',
        'response_text', 'feedback', 'is_correct', 'points_earned',
        'graded_by_user_id', 'graded_at',
    ];

    protected $casts = [
        'selected_options' => 'array',
        'is_correct' => 'boolean',
        'points_earned' => 'integer',
        'graded_at' => 'datetime',
    ];

    public function gradedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'graded_by_user_id');
    }

    public function getIsPendingGradeAttribute(): bool
    {
        return $this->question?->question_type === 'open_text' && $this->graded_at === null;
    }

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
