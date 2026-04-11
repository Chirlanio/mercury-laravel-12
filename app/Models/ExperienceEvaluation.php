<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExperienceEvaluation extends Model
{
    // Milestones
    public const MILESTONE_45 = '45';

    public const MILESTONE_90 = '90';

    public const MILESTONE_LABELS = [
        self::MILESTONE_45 => '45 dias',
        self::MILESTONE_90 => '90 dias',
    ];

    // Statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_COMPLETED => 'Concluido',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'yellow',
        self::STATUS_COMPLETED => 'green',
    ];

    // Recommendation
    public const RECOMMENDATION_YES = 'yes';

    public const RECOMMENDATION_NO = 'no';

    protected $fillable = [
        'employee_id', 'manager_id', 'store_id', 'milestone',
        'date_admission', 'milestone_date',
        'manager_status', 'employee_status',
        'manager_completed_at', 'employee_completed_at',
        'employee_token', 'recommendation',
    ];

    protected $casts = [
        'date_admission' => 'date',
        'milestone_date' => 'date',
        'manager_completed_at' => 'datetime',
        'employee_completed_at' => 'datetime',
    ];

    // Accessors

    public function getMilestoneLabelAttribute(): string
    {
        return self::MILESTONE_LABELS[$this->milestone] ?? $this->milestone;
    }

    public function getOverallStatusAttribute(): string
    {
        if ($this->manager_status === self::STATUS_COMPLETED && $this->employee_status === self::STATUS_COMPLETED) {
            return 'completed';
        }
        if ($this->manager_status === self::STATUS_COMPLETED || $this->employee_status === self::STATUS_COMPLETED) {
            return 'partial';
        }

        return 'pending';
    }

    public function getOverallStatusLabelAttribute(): string
    {
        return match ($this->overall_status) {
            'completed' => 'Concluido',
            'partial' => 'Parcial',
            'pending' => 'Pendente',
        };
    }

    public function getOverallStatusColorAttribute(): string
    {
        return match ($this->overall_status) {
            'completed' => 'green',
            'partial' => 'yellow',
            'pending' => 'gray',
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->overall_status !== 'completed' && $this->milestone_date->isPast();
    }

    public function getIsNearDeadlineAttribute(): bool
    {
        return $this->overall_status !== 'completed'
            && ! $this->milestone_date->isPast()
            && $this->milestone_date->diffInDays(now()) <= 5;
    }

    // Relationships

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'code');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExperienceResponse::class, 'evaluation_id');
    }

    public function managerResponses(): HasMany
    {
        return $this->hasMany(ExperienceResponse::class, 'evaluation_id')
            ->where('form_type', 'manager');
    }

    public function employeeResponses(): HasMany
    {
        return $this->hasMany(ExperienceResponse::class, 'evaluation_id')
            ->where('form_type', 'employee');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ExperienceNotification::class, 'evaluation_id');
    }

    // Scopes

    public function scopeForMilestone(Builder $query, string $milestone): Builder
    {
        return $query->where('milestone', $milestone);
    }

    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('manager_status', self::STATUS_PENDING)
                ->orWhere('employee_status', self::STATUS_PENDING);
        });
    }

    public function scopeFullyCompleted(Builder $query): Builder
    {
        return $query->where('manager_status', self::STATUS_COMPLETED)
            ->where('employee_status', self::STATUS_COMPLETED);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->where('milestone_date', '<', now()->toDateString());
    }

    public function scopeNearDeadline(Builder $query, int $days = 5): Builder
    {
        return $query->pending()
            ->where('milestone_date', '>=', now()->toDateString())
            ->where('milestone_date', '<=', now()->addDays($days)->toDateString());
    }
}
