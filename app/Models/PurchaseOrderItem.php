<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item de ordem de compra. Um registro por (referência, tamanho).
 *
 * Dados denormalizados (description, material, color) são preservados para
 * manter histórico mesmo se o catálogo mudar. FK product_id é opcional.
 */
class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'reference',
        'size',
        'description',
        'material',
        'color',
        'group_name',
        'subgroup_name',
        'unit_cost',
        'markup',
        'selling_price',
        'pricing_locked',
        'quantity_ordered',
        'quantity_received',
        'invoice_number',
        'invoice_emission_date',
        'confirmation_date',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'markup' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'pricing_locked' => 'boolean',
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'invoice_emission_date' => 'date',
        'confirmation_date' => 'date',
    ];

    public function getTotalCostAttribute(): float
    {
        return (float) $this->unit_cost * $this->quantity_ordered;
    }

    public function getTotalSellingAttribute(): float
    {
        return (float) $this->selling_price * $this->quantity_ordered;
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class);
    }
}
