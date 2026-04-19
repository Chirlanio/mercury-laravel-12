<?php

namespace App\Models;

use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitação de devolução/troca do e-commerce (paridade v1 — adms_returns).
 *
 * Nome da classe é `ReturnOrder` (e não `Return`) porque "return" é
 * keyword do PHP. Tabela é `return_orders`. Segue padrão de PurchaseOrder.
 *
 * State machine em ReturnStatus — mutação exclusivamente via
 * ReturnOrderTransitionService.
 *
 * Soft delete manual (convenção do projeto).
 */
class ReturnOrder extends Model
{
    use Auditable;

    protected $table = 'return_orders';

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
        'amount_items',
        'refund_amount',
        'status',
        'reason_category',
        'return_reason_id',
        'reverse_tracking_code',
        'approved_at',
        'completed_at',
        'cancelled_at',
        'cancelled_reason',
        'notes',
        'created_by_user_id',
        'approved_by_user_id',
        'processed_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'status' => ReturnStatus::class,
        'type' => ReturnType::class,
        'reason_category' => ReturnReasonCategory::class,
        'movement_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'deleted_at' => 'datetime',
        'sale_total' => 'decimal:2',
        'amount_items' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // State machine helpers
    // ------------------------------------------------------------------

    public function canTransitionTo(ReturnStatus|string $target): bool
    {
        $target = $target instanceof ReturnStatus ? $target : ReturnStatus::from($target);

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

    public function requiresRefundAmount(): bool
    {
        return $this->type?->requiresRefundAmount() ?? false;
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_code', $storeCode);
    }

    public function scopeForStatus(Builder $query, ReturnStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ReturnStatus ? $status->value : $status);
    }

    public function scopeForType(Builder $query, ReturnType|string $type): Builder
    {
        return $query->where('type', $type instanceof ReturnType ? $type->value : $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (ReturnStatus $s) => $s->value,
            ReturnStatus::active()
        ));
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', ReturnStatus::PENDING->value);
    }

    public function scopeAwaitingProduct(Builder $query): Builder
    {
        return $query->where('status', ReturnStatus::AWAITING_PRODUCT->value);
    }

    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->whereMonth('created_at', $month)->whereYear('created_at', $year);
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('return_orders.deleted_at');
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(ReturnOrderItem::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ReturnOrderFile::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReturnOrderStatusHistory::class)->orderByDesc('created_at');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'return_reason_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_code', 'code');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
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
