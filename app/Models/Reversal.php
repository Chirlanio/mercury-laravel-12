<?php

namespace App\Models;

use App\Enums\ReversalPartialMode;
use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitação de estorno de venda (paridade v1 — adms_estornos).
 *
 * State machine em ReversalStatus. Transições aplicadas exclusivamente
 * via ReversalTransitionService — nunca mutar status direto.
 *
 * Soft delete segue convenção de Vacancy/PersonnelMovement/PurchaseOrder:
 * coluna deleted_at manipulada pelo service, sem trait SoftDeletes.
 */
class Reversal extends Model
{
    use Auditable;

    protected $fillable = [
        'invoice_number',
        'store_code',
        'movement_date',
        'cpf_customer',
        'customer_name',
        'cpf_consultant',
        'employee_id',
        'sale_total',
        'type',
        'partial_mode',
        'amount_original',
        'amount_correct',
        'amount_reversal',
        'status',
        'reversal_reason_id',
        'expected_refund_date',
        'reversed_at',
        'cancelled_at',
        'cancelled_reason',
        'payment_type_id',
        'payment_brand',
        'installments_count',
        'nsu',
        'authorization_code',
        'pix_key_type',
        'pix_key',
        'pix_beneficiary',
        'pix_bank_id',
        'notes',
        'synced_to_cigam_at',
        'helpdesk_ticket_id',
        'created_by_user_id',
        'authorized_by_user_id',
        'processed_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'status' => ReversalStatus::class,
        'type' => ReversalType::class,
        'partial_mode' => ReversalPartialMode::class,
        'movement_date' => 'date',
        'expected_refund_date' => 'date',
        'reversed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'synced_to_cigam_at' => 'datetime',
        'deleted_at' => 'datetime',
        'sale_total' => 'decimal:2',
        'amount_original' => 'decimal:2',
        'amount_correct' => 'decimal:2',
        'amount_reversal' => 'decimal:2',
        'installments_count' => 'integer',
    ];

    // ------------------------------------------------------------------
    // State machine helpers
    // ------------------------------------------------------------------

    public function canTransitionTo(ReversalStatus|string $target): bool
    {
        $target = $target instanceof ReversalStatus ? $target : ReversalStatus::from($target);

        return $this->status->canTransitionTo($target);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    public function isPartialByItem(): bool
    {
        return $this->type === ReversalType::PARTIAL
            && $this->partial_mode === ReversalPartialMode::BY_ITEM;
    }

    public function isPartialByValue(): bool
    {
        return $this->type === ReversalType::PARTIAL
            && $this->partial_mode === ReversalPartialMode::BY_VALUE;
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_code', $storeCode);
    }

    public function scopeForStatus(Builder $query, ReversalStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ReversalStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (ReversalStatus $s) => $s->value,
            ReversalStatus::active()
        ));
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', ReversalStatus::PENDING_AUTHORIZATION->value);
    }

    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->whereMonth('created_at', $month)->whereYear('created_at', $year);
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('reversals.deleted_at');
    }

    /**
     * Estornos que ainda não foram sincronizados com o CIGAM. Usado pelo
     * command reversals:cigam-push (Fase 4).
     */
    public function scopePendingCigamSync(Builder $query): Builder
    {
        return $query->where('status', ReversalStatus::REVERSED->value)
            ->whereNull('synced_to_cigam_at');
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(ReversalItem::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ReversalFile::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReversalStatusHistory::class)->orderByDesc('created_at');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReversalReason::class, 'reversal_reason_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_code', 'code');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function pixBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'pix_bank_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}
