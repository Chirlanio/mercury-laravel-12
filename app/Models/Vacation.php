<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vacation extends Model
{
    use Auditable;

    protected $fillable = [
        'vacation_period_id',
        'employee_id',
        'store_id',
        'date_start',
        'date_end',
        'date_return',
        'days_quantity',
        'installment',
        'sell_allowance',
        'sell_days',
        'advance_13th',
        'payment_deadline',
        'default_days_override',
        'override_reason',
        'status',
        'requested_by_user_id',
        'manager_approved_by_user_id',
        'manager_approved_at',
        'manager_notes',
        'hr_approved_by_user_id',
        'hr_approved_at',
        'hr_notes',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'finalized_at',
        'employee_acknowledged_at',
        'previous_employee_status',
        'is_retroactive',
        'retroactive_reason',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'delete_reason',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
        'date_return' => 'date',
        'payment_deadline' => 'date',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'finalized_at' => 'datetime',
        'employee_acknowledged_at' => 'datetime',
        'deleted_at' => 'datetime',
        'sell_allowance' => 'boolean',
        'advance_13th' => 'boolean',
        'default_days_override' => 'boolean',
        'is_retroactive' => 'boolean',
    ];

    // ==========================================
    // Status constants
    // ==========================================

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_MANAGER = 'pending_manager';

    public const STATUS_APPROVED_MANAGER = 'approved_manager';

    public const STATUS_APPROVED_RH = 'approved_rh';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REJECTED_MANAGER = 'rejected_manager';

    public const STATUS_REJECTED_RH = 'rejected_rh';

    public const STATUS_LABELS = [
        'draft' => 'Rascunho',
        'pending_manager' => 'Pendente Gestor',
        'approved_manager' => 'Aprovada Gestor',
        'approved_rh' => 'Aprovada RH',
        'in_progress' => 'Em Gozo',
        'completed' => 'Finalizada',
        'cancelled' => 'Cancelada',
        'rejected_manager' => 'Rejeitada Gestor',
        'rejected_rh' => 'Rejeitada RH',
    ];

    public const STATUS_COLORS = [
        'draft' => 'gray',
        'pending_manager' => 'yellow',
        'approved_manager' => 'blue',
        'approved_rh' => 'indigo',
        'in_progress' => 'green',
        'completed' => 'emerald',
        'cancelled' => 'red',
        'rejected_manager' => 'orange',
        'rejected_rh' => 'red',
    ];

    public const VALID_TRANSITIONS = [
        'draft' => ['pending_manager', 'cancelled'],
        'pending_manager' => ['approved_manager', 'cancelled', 'rejected_manager'],
        'approved_manager' => ['approved_rh', 'cancelled', 'rejected_rh'],
        'approved_rh' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        'rejected_manager' => ['draft'],
        'rejected_rh' => ['approved_manager'],
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function vacationPeriod(): BelongsTo
    {
        return $this->belongsTo(VacationPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by_user_id');
    }

    public function hrApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VacationLog::class)->orderByDesc('created_at');
    }

    // ==========================================
    // Accessors
    // ==========================================

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
        return $this->deleted_at !== null;
    }

    public function getIsTerminalAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$this->status] ?? [];

        return in_array($newStatus, $allowed);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_MANAGER,
            self::STATUS_APPROVED_MANAGER,
            self::STATUS_APPROVED_RH,
        ]);
    }

    public function scopeNotCancelledOrRejected($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_CANCELLED,
            self::STATUS_REJECTED_MANAGER,
            self::STATUS_REJECTED_RH,
        ]);
    }

    public function scopeOverlapping($query, string $dateStart, string $dateEnd, ?int $excludeId = null)
    {
        $query->where('date_start', '<=', $dateEnd)
            ->where('date_end', '>=', $dateStart)
            ->notCancelledOrRejected();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }
}
