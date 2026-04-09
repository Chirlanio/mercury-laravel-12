<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAudit extends Model
{
    use Auditable;

    protected $fillable = [
        'store_id',
        'audit_cycle_id',
        'vendor_id',
        'audit_type',
        'status',
        'manager_responsible_id',
        'stockist_id',
        'random_sample_size',
        'requires_second_count',
        'requires_third_count',
        'count_1_finalized',
        'count_2_finalized',
        'count_3_finalized',
        'reconciliation_phase',
        'authorized_by_user_id',
        'authorized_at',
        'started_at',
        'finished_at',
        'accuracy_percentage',
        'total_items_counted',
        'total_divergences',
        'financial_loss',
        'financial_surplus',
        'financial_loss_cost',
        'financial_surplus_cost',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'deleted_at',
        'deleted_by_user_id',
        'delete_reason',
    ];

    protected $casts = [
        'requires_second_count' => 'boolean',
        'requires_third_count' => 'boolean',
        'count_1_finalized' => 'boolean',
        'count_2_finalized' => 'boolean',
        'count_3_finalized' => 'boolean',
        'authorized_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'deleted_at' => 'datetime',
        'accuracy_percentage' => 'decimal:2',
        'financial_loss' => 'decimal:2',
        'financial_surplus' => 'decimal:2',
        'financial_loss_cost' => 'decimal:2',
        'financial_surplus_cost' => 'decimal:2',
    ];

    public const STATUS_LABELS = [
        'draft' => 'Rascunho',
        'awaiting_authorization' => 'Aguardando Autorização',
        'counting' => 'Em Contagem',
        'reconciliation' => 'Conciliação',
        'finished' => 'Finalizada',
        'cancelled' => 'Cancelada',
    ];

    public const STATUS_COLORS = [
        'draft' => 'bg-gray-100 text-gray-800',
        'awaiting_authorization' => 'bg-yellow-100 text-yellow-800',
        'counting' => 'bg-blue-100 text-blue-800',
        'reconciliation' => 'bg-indigo-100 text-indigo-800',
        'finished' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
    ];

    public const VALID_TRANSITIONS = [
        'draft' => ['awaiting_authorization', 'cancelled'],
        'awaiting_authorization' => ['counting', 'cancelled'],
        'counting' => ['reconciliation', 'cancelled'],
        'reconciliation' => ['finished', 'cancelled'],
        'finished' => [],
        'cancelled' => [],
    ];

    public const AUDIT_TYPES = [
        'total' => 'Total',
        'parcial' => 'Parcial',
        'especifica' => 'Específica',
        'aleatoria' => 'Aleatória',
        'diaria' => 'Diária',
    ];

    public const TEAM_ROLES = [
        'contador' => 'Contador',
        'conferente' => 'Conferente',
        'auditor' => 'Auditor',
        'supervisor' => 'Supervisor',
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$this->status] ?? [];

        return in_array($newStatus, $allowed, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['finished', 'cancelled'], true);
    }

    // Relationships

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(StockAuditCycle::class, 'audit_cycle_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(StockAuditVendor::class, 'vendor_id');
    }

    public function managerResponsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_responsible_id');
    }

    public function stockist(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'stockist_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAuditItem::class, 'audit_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(StockAuditArea::class, 'audit_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(StockAuditTeam::class, 'audit_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(StockAuditSignature::class, 'audit_id');
    }

    public function storeJustifications(): HasMany
    {
        return $this->hasMany(StockAuditStoreJustification::class, 'audit_id');
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(StockAuditImportLog::class, 'audit_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StockAuditLog::class, 'audit_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('audit_type', $type);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['finished', 'cancelled']);
    }
}
