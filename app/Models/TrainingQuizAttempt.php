<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingQuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id', 'user_id', 'score', 'total_points', 'earned_points',
        'passed', 'attempt_number', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'total_points' => 'integer',
        'earned_points' => 'integer',
        'passed' => 'boolean',
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TrainingQuiz::class, 'quiz_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TrainingQuizResponse::class, 'attempt_id');
    }

    // Scopes

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    // Accessors

    public function getIsCompletedAttribute(): bool
    {
        return ! is_null($this->completed_at);
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->completed_at || ! $this->started_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->completed_at);
    }
}
