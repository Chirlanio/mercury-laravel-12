<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HdTicket extends Model
{
    use Auditable, SoftDeletes;

    // Statuses
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Aberto',
        self::STATUS_IN_PROGRESS => 'Em Andamento',
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_RESOLVED => 'Resolvido',
        self::STATUS_CLOSED => 'Fechado',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const STATUS_COLORS = [
        self::STATUS_OPEN => 'info',
        self::STATUS_IN_PROGRESS => 'warning',
        self::STATUS_PENDING => 'orange',
        self::STATUS_RESOLVED => 'success',
        self::STATUS_CLOSED => 'gray',
        self::STATUS_CANCELLED => 'danger',
    ];

    public const VALID_TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_PENDING, self::STATUS_RESOLVED, self::STATUS_CANCELLED],
        self::STATUS_PENDING => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_RESOLVED => [self::STATUS_CLOSED, self::STATUS_IN_PROGRESS],
        self::STATUS_CLOSED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const TERMINAL_STATUSES = [self::STATUS_CLOSED, self::STATUS_CANCELLED];

    // Priorities
    public const PRIORITY_LOW = 1;

    public const PRIORITY_MEDIUM = 2;

    public const PRIORITY_HIGH = 3;

    public const PRIORITY_URGENT = 4;

    public const PRIORITY_LABELS = [
        1 => 'Baixa',
        2 => 'Média',
        3 => 'Alta',
        4 => 'Urgente',
    ];

    public const PRIORITY_COLORS = [
        1 => 'gray',
        2 => 'info',
        3 => 'warning',
        4 => 'danger',
    ];

    public const SLA_HOURS = [
        1 => 72,
        2 => 48,
        3 => 24,
        4 => 8,
    ];

    protected $fillable = [
        'requester_id', 'assigned_technician_id', 'department_id', 'category_id',
        'store_id', 'title', 'description', 'status', 'priority',
        'sla_due_at', 'resolved_at', 'closed_at',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'priority' => 'integer',
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // State machine

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
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

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? 'Média';
    }

    public function getPriorityColorAttribute(): string
    {
        return self::PRIORITY_COLORS[$this->priority] ?? 'info';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->sla_due_at && $this->sla_due_at->isPast() && ! $this->isTerminal();
    }

    public function getSlaRemainingHoursAttribute(): ?float
    {
        if (! $this->sla_due_at || $this->isTerminal()) {
            return null;
        }

        return round(now()->diffInMinutes($this->sla_due_at, false) / 60, 1);
    }

    // Relationships

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HdCategory::class, 'category_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'code');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(HdInteraction::class, 'ticket_id')->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HdAttachment::class, 'ticket_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    // Scopes

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('requester_id', $userId)
                ->orWhere('assigned_technician_id', $userId);
        });
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForPriority(Builder $query, int $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }
}
