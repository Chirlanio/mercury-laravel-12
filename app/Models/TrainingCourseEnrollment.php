<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCourseEnrollment extends Model
{
    // Statuses
    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DROPPED = 'dropped';

    public const STATUS_LABELS = [
        self::STATUS_ENROLLED => 'Inscrito',
        self::STATUS_IN_PROGRESS => 'Em Andamento',
        self::STATUS_COMPLETED => 'Concluído',
        self::STATUS_DROPPED => 'Desistente',
    ];

    public const STATUS_COLORS = [
        self::STATUS_ENROLLED => 'blue',
        self::STATUS_IN_PROGRESS => 'yellow',
        self::STATUS_COMPLETED => 'green',
        self::STATUS_DROPPED => 'red',
    ];

    protected $fillable = [
        'course_id', 'user_id', 'employee_id', 'status',
        'enrolled_at', 'completed_at', 'completion_percent',
        'certificate_generated', 'certificate_path',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'completion_percent' => 'decimal:2',
        'certificate_generated' => 'boolean',
    ];

    // Accessors

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    // Relationships

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Scopes

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ENROLLED, self::STATUS_IN_PROGRESS]);
    }
}
