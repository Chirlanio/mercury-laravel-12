<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PersonnelMovement extends Model
{
    use Auditable;

    // Movement types
    public const TYPE_DISMISSAL = 'dismissal';

    public const TYPE_PROMOTION = 'promotion';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_REACTIVATION = 'reactivation';

    public const TYPE_LABELS = [
        self::TYPE_DISMISSAL => 'Desligamento',
        self::TYPE_PROMOTION => 'Promoção',
        self::TYPE_TRANSFER => 'Transferência',
        self::TYPE_REACTIVATION => 'Reativação',
    ];

    public const TYPE_COLORS = [
        self::TYPE_DISMISSAL => 'red',
        self::TYPE_PROMOTION => 'purple',
        self::TYPE_TRANSFER => 'blue',
        self::TYPE_REACTIVATION => 'green',
    ];

    // Statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_IN_PROGRESS => 'Em Andamento',
        self::STATUS_COMPLETED => 'Concluído',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'yellow',
        self::STATUS_IN_PROGRESS => 'blue',
        self::STATUS_COMPLETED => 'green',
        self::STATUS_CANCELLED => 'red',
    ];

    public const VALID_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    // Contract types (dismissal)
    public const CONTRACT_TYPES = [
        'clt' => 'CLT (Efetivo)',
        'trial' => 'Experiência',
        'intern' => 'Estagiário',
        'apprentice' => 'Aprendiz',
    ];

    // Dismissal subtypes
    public const DISMISSAL_SUBTYPES = [
        'company_initiative' => 'Iniciativa da Empresa',
        'employee_resignation' => 'Pedido de Demissão',
        'trial_end' => 'Término de Experiência',
        'just_cause' => 'Justa Causa',
    ];

    // Early warning types
    public const EARLY_WARNING_TYPES = [
        'worked' => 'Trabalhado',
        'indemnified' => 'Indenizado',
        'dispensed' => 'Dispensado',
    ];

    // Access control field names
    public const ACCESS_FIELDS = [
        'access_power_bi', 'access_zznet', 'access_cigam', 'access_camera',
        'access_deskfy', 'access_meu_atendimento', 'access_dito', 'access_notebook',
        'access_email_corporate', 'access_parking_card', 'access_parking_shopping',
        'access_key_office', 'access_key_store', 'access_instagram',
    ];

    public const ACTIVATION_FIELDS = [
        'activate_it', 'activate_operation', 'deactivate_instagram', 'activate_hr',
    ];

    protected $fillable = [
        'type', 'employee_id', 'store_id', 'status', 'effective_date', 'observation',
        'requester_id', 'request_area_id',
        // Dismissal
        'contact', 'email', 'contract_type', 'dismissal_subtype', 'early_warning',
        'last_day_worked',
        ...self::ACCESS_FIELDS,
        ...self::ACTIVATION_FIELDS,
        'fouls', 'days_off', 'overtime_hours', 'fixed_fund', 'open_vacancy',
        // Promotion
        'new_position_id',
        // Transfer
        'origin_store_id', 'destination_store_id',
        // Reactivation
        'reactivation_date',
        // Audit
        'created_by_user_id', 'updated_by_user_id',
        'deleted_at', 'deleted_by_user_id', 'deleted_reason',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'last_day_worked' => 'date',
        'reactivation_date' => 'date',
        'fixed_fund' => 'decimal:2',
        'open_vacancy' => 'boolean',
        'access_power_bi' => 'boolean',
        'access_zznet' => 'boolean',
        'access_cigam' => 'boolean',
        'access_camera' => 'boolean',
        'access_deskfy' => 'boolean',
        'access_meu_atendimento' => 'boolean',
        'access_dito' => 'boolean',
        'access_notebook' => 'boolean',
        'access_email_corporate' => 'boolean',
        'access_parking_card' => 'boolean',
        'access_parking_shopping' => 'boolean',
        'access_key_office' => 'boolean',
        'access_key_store' => 'boolean',
        'access_instagram' => 'boolean',
        'activate_it' => 'boolean',
        'activate_operation' => 'boolean',
        'deactivate_instagram' => 'boolean',
        'activate_hr' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->type] ?? 'gray';
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    // Relationships

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'code');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function requestArea(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'request_area_id');
    }

    public function newPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'new_position_id');
    }

    public function originStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'origin_store_id', 'code');
    }

    public function destinationStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store_id', 'code');
    }

    public function reasons(): BelongsToMany
    {
        return $this->belongsToMany(DismissalReason::class, 'personnel_movement_reasons');
    }

    public function followUp(): HasOne
    {
        return $this->hasOne(DismissalFollowUp::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(PersonnelMovementFile::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PersonnelMovementStatusHistory::class)->orderByDesc('created_at');
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

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
