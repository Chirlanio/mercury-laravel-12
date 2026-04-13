<?php

namespace App\Models;

use App\Enums\HdTicketPriority;
use App\Enums\HdTicketStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HdTicket extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // ---------------------------------------------------------------------
    // Status / priority are the canonical enums. The const shims below
    // preserve back-compat with existing call sites that reference
    // HdTicket::STATUS_OPEN etc. New code should prefer the enums directly.
    // ---------------------------------------------------------------------

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 1;

    public const PRIORITY_MEDIUM = 2;

    public const PRIORITY_HIGH = 3;

    public const PRIORITY_URGENT = 4;

    public static function statusLabels(): array
    {
        return HdTicketStatus::labels();
    }

    public static function statusColors(): array
    {
        return HdTicketStatus::colors();
    }

    public static function priorityLabels(): array
    {
        return HdTicketPriority::labels();
    }

    public static function priorityColors(): array
    {
        return HdTicketPriority::colors();
    }

    public static function slaHoursMap(): array
    {
        return HdTicketPriority::slaHoursMap();
    }

    public static function validTransitions(): array
    {
        return HdTicketStatus::transitionMap();
    }

    public static function terminalStatuses(): array
    {
        return array_map(fn ($s) => $s->value, HdTicketStatus::terminal());
    }

    /**
     * Back-compat: class constants that existing callsites read like
     * `HdTicket::STATUS_LABELS`, `::PRIORITY_LABELS`, `::SLA_HOURS`,
     * `::VALID_TRANSITIONS`, `::TERMINAL_STATUSES`.
     *
     * PHP constants cannot reference functions, so these are mirrored as
     * `public const` arrays below. They must stay in sync with the enums;
     * the enums are the source of truth.
     */
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
        self::STATUS_CLOSED => [self::STATUS_IN_PROGRESS],
        self::STATUS_CANCELLED => [],
    ];

    public const TERMINAL_STATUSES = [self::STATUS_CLOSED, self::STATUS_CANCELLED];

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
        'requester_id', 'employee_id', 'assigned_technician_id', 'department_id', 'category_id',
        'store_id', 'title', 'description', 'status', 'priority',
        'source', 'ai_confidence', 'ai_model', 'ai_classified_at',
        'ai_category_id', 'ai_priority',
        'sla_due_at', 'resolved_at', 'closed_at', 'merged_into_ticket_id',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'priority' => 'integer',
        'ai_confidence' => 'decimal:2',
        'ai_priority' => 'integer',
        'ai_classified_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $attributes = [
        'source' => 'web',
    ];

    // State machine

    public function statusEnum(): ?HdTicketStatus
    {
        return HdTicketStatus::tryFrom($this->status);
    }

    public function priorityEnum(): ?HdTicketPriority
    {
        return $this->priority !== null ? HdTicketPriority::tryFrom((int) $this->priority) : null;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $current = $this->statusEnum();
        $target = HdTicketStatus::tryFrom($newStatus);

        if ($current === null || $target === null) {
            return false;
        }

        return $current->canTransitionTo($target);
    }

    public function isTerminal(): bool
    {
        return $this->statusEnum()?->isTerminal() ?? false;
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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

    public function aiCategory(): BelongsTo
    {
        return $this->belongsTo(HdCategory::class, 'ai_category_id');
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

    public function ticketChannels(): HasMany
    {
        return $this->hasMany(HdTicketChannel::class, 'ticket_id');
    }

    public function intakeData(): HasMany
    {
        return $this->hasMany(HdTicketIntakeData::class, 'ticket_id');
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
