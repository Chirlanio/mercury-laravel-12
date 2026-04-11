<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Training extends Model
{
    use Auditable;

    // Statuses
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_PUBLISHED => 'Publicado',
        self::STATUS_IN_PROGRESS => 'Em Andamento',
        self::STATUS_COMPLETED => 'Concluído',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_PUBLISHED => 'blue',
        self::STATUS_IN_PROGRESS => 'yellow',
        self::STATUS_COMPLETED => 'green',
        self::STATUS_CANCELLED => 'red',
    ];

    public const VALID_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PUBLISHED, self::STATUS_CANCELLED],
        self::STATUS_PUBLISHED => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'hash_id', 'title', 'description', 'image_path',
        'event_date', 'start_time', 'end_time', 'duration_minutes',
        'location', 'max_participants',
        'facilitator_id', 'subject_id', 'status',
        'attendance_qrcode_token', 'evaluation_qrcode_token',
        'allow_late_attendance', 'attendance_grace_minutes',
        'certificate_template_id', 'evaluation_enabled',
        'created_by_user_id', 'updated_by_user_id',
        'deleted_at', 'deleted_by_user_id', 'deleted_reason',
    ];

    protected $casts = [
        'event_date' => 'date',
        'duration_minutes' => 'integer',
        'max_participants' => 'integer',
        'allow_late_attendance' => 'boolean',
        'attendance_grace_minutes' => 'integer',
        'evaluation_enabled' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // Boot

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Training $training) {
            if (empty($training->hash_id)) {
                $training->hash_id = (string) Str::uuid();
            }
            if (empty($training->attendance_qrcode_token)) {
                $training->attendance_qrcode_token = Str::random(64);
            }
            if (empty($training->evaluation_qrcode_token)) {
                $training->evaluation_qrcode_token = Str::random(64);
            }
            if ($training->start_time && $training->end_time) {
                $start = \Carbon\Carbon::parse($training->start_time);
                $end = \Carbon\Carbon::parse($training->end_time);
                $training->duration_minutes = $start->diffInMinutes($end);
            }
        });

        static::updating(function (Training $training) {
            if ($training->isDirty(['start_time', 'end_time']) && $training->start_time && $training->end_time) {
                $start = \Carbon\Carbon::parse($training->start_time);
                $end = \Carbon\Carbon::parse($training->end_time);
                $training->duration_minutes = $start->diffInMinutes($end);
            }
        });
    }

    // State machine

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    // Accessors

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    public function getDurationHoursAttribute(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        return $minutes > 0 ? "{$hours}h{$minutes}min" : "{$hours}h";
    }

    public function getParticipantCountAttribute(): int
    {
        return $this->participants()->count();
    }

    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->evaluations()->avg('rating');

        return $avg ? round($avg, 1) : null;
    }

    public function getHasVacancyAttribute(): bool
    {
        if (is_null($this->max_participants)) {
            return true;
        }

        return $this->participants()->count() < $this->max_participants;
    }

    // Relationships

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(TrainingFacilitator::class, 'facilitator_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(TrainingSubject::class, 'subject_id');
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(TrainingEvaluation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForFacilitator(Builder $query, int $facilitatorId): Builder
    {
        return $query->where('facilitator_id', $facilitatorId);
    }

    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>=', now()->toDateString())
            ->whereIn('status', [self::STATUS_PUBLISHED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeForDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('event_date', '>=', $from);
        }
        if ($to) {
            $query->where('event_date', '<=', $to);
        }

        return $query;
    }
}
