<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingQuiz extends Model
{
    protected $fillable = [
        'content_id', 'course_id', 'title', 'description',
        'passing_score', 'max_attempts', 'show_answers',
        'time_limit_minutes', 'is_active',
        'created_by_user_id', 'deleted_at', 'deleted_by_user_id',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'max_attempts' => 'integer',
        'show_answers' => 'boolean',
        'time_limit_minutes' => 'integer',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // Relationships

    public function content(): BelongsTo
    {
        return $this->belongsTo(TrainingContent::class, 'content_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TrainingQuizQuestion::class, 'quiz_id')->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingQuizAttempt::class, 'quiz_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    // Accessors

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    public function getQuestionCountAttribute(): int
    {
        return $this->questions()->count();
    }

    public function getTotalPointsAttribute(): int
    {
        return $this->questions()->sum('points');
    }
}
