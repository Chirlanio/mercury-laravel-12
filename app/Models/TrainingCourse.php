<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TrainingCourse extends Model
{
    use Auditable;

    // Statuses
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_PUBLISHED => 'Publicado',
        self::STATUS_ARCHIVED => 'Arquivado',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_PUBLISHED => 'green',
        self::STATUS_ARCHIVED => 'yellow',
    ];

    public const VALID_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PUBLISHED],
        self::STATUS_PUBLISHED => [self::STATUS_ARCHIVED],
        self::STATUS_ARCHIVED => [self::STATUS_PUBLISHED],
    ];

    // Visibility
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_LABELS = [
        self::VISIBILITY_PUBLIC => 'Público',
        self::VISIBILITY_PRIVATE => 'Privado',
    ];

    protected $fillable = [
        'hash_id', 'title', 'description', 'thumbnail_path',
        'subject_id', 'facilitator_id', 'visibility', 'status',
        'requires_sequential', 'certificate_on_completion',
        'certificate_template_id', 'estimated_duration_minutes',
        'published_at',
        'created_by_user_id', 'updated_by_user_id',
        'deleted_at', 'deleted_by_user_id', 'deleted_reason',
    ];

    protected $casts = [
        'requires_sequential' => 'boolean',
        'certificate_on_completion' => 'boolean',
        'estimated_duration_minutes' => 'integer',
        'published_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Boot

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $course) {
            if (empty($course->hash_id)) {
                $course->hash_id = (string) Str::uuid();
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

    public function getVisibilityLabelAttribute(): string
    {
        return self::VISIBILITY_LABELS[$this->visibility] ?? $this->visibility;
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    public function getContentCountAttribute(): int
    {
        return $this->contents()->count();
    }

    public function getEnrollmentCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    // Relationships

    public function subject(): BelongsTo
    {
        return $this->belongsTo(TrainingSubject::class, 'subject_id');
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(TrainingFacilitator::class, 'facilitator_id');
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(TrainingContent::class, 'training_course_contents', 'course_id', 'content_id')
            ->withPivot(['sort_order', 'is_required'])
            ->orderByPivot('sort_order');
    }

    public function courseContents(): HasMany
    {
        return $this->hasMany(TrainingCourseContent::class, 'course_id')->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingCourseEnrollment::class, 'course_id');
    }

    public function visibilityRules(): HasMany
    {
        return $this->hasMany(TrainingCourseVisibility::class, 'course_id');
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForVisibility(Builder $query, string $visibility): Builder
    {
        return $query->where('visibility', $visibility);
    }

    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForFacilitator(Builder $query, int $facilitatorId): Builder
    {
        return $query->where('facilitator_id', $facilitatorId);
    }
}
