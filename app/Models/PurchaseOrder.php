<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ordem de compra de coleção (paridade v1 — adms_purchase_order_controls).
 *
 * State machine em PurchaseOrderStatus. Transições aplicadas exclusivamente
 * via PurchaseOrderTransitionService — nunca mutar status direto.
 *
 * Soft delete segue a convenção de Vacancy/PersonnelMovement: coluna
 * deleted_at manipulada pelo service, sem trait SoftDeletes.
 */
class PurchaseOrder extends Model
{
    use Auditable;

    protected $fillable = [
        'order_number',
        'short_description',
        'season',
        'collection',
        'release_name',
        'supplier_id',
        'store_id',
        'brand_id',
        'order_date',
        'predict_date',
        'delivered_at',
        'payment_terms_raw',
        'auto_generate_payments',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'status' => PurchaseOrderStatus::class,
        'order_date' => 'date',
        'predict_date' => 'date',
        'delivered_at' => 'datetime',
        'auto_generate_payments' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // State machine helpers
    // ------------------------------------------------------------------

    public function canTransitionTo(PurchaseOrderStatus|string $target): bool
    {
        $target = $target instanceof PurchaseOrderStatus ? $target : PurchaseOrderStatus::from($target);

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

    /**
     * Ordem vencida: predict_date < hoje e ordem ainda não entregue/cancelada.
     */
    public function isOverdue(): bool
    {
        if ($this->isTerminal() || $this->status === PurchaseOrderStatus::CANCELLED || ! $this->predict_date) {
            return false;
        }

        return $this->predict_date->isPast();
    }

    /**
     * Soma total do custo dos itens.
     */
    public function getTotalCostAttribute(): float
    {
        return (float) $this->items->sum(fn ($item) => $item->unit_cost * $item->quantity_ordered);
    }

    /**
     * Soma total de venda dos itens.
     */
    public function getTotalSellingAttribute(): float
    {
        return (float) $this->items->sum(fn ($item) => $item->selling_price * $item->quantity_ordered);
    }

    /**
     * Soma total de unidades pedidas.
     */
    public function getTotalUnitsAttribute(): int
    {
        return (int) $this->items->sum('quantity_ordered');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_id', $storeCode);
    }

    public function scopeForStatus(Builder $query, PurchaseOrderStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof PurchaseOrderStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (PurchaseOrderStatus $s) => $s->value,
            PurchaseOrderStatus::active()
        ));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('predict_date')
            ->whereDate('predict_date', '<', now()->toDateString());
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        // Qualificado pra evitar ambiguidade quando há JOIN com outra
        // tabela que tem soft delete (ex: suppliers).
        return $query->whereNull('purchase_orders.deleted_at');
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class)->orderByDesc('received_at');
    }

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('date_payment');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class)->orderByDesc('created_at');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'code');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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
}
