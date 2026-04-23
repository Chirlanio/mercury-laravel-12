<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivô entre ConsignmentReturn e ConsignmentItem — detalha qual item
 * de consignação foi devolvido em qual evento e em que quantidade.
 *
 * Validação do vínculo (consignment_item.consignment_id ===
 * consignment_return.consignment_id) e da quantidade (não pode exceder
 * o pendente do item) é feita em ConsignmentReturnService::register.
 */
class ConsignmentReturnItem extends Model
{
    protected $table = 'consignment_return_items';

    protected $fillable = [
        'consignment_return_id',
        'consignment_item_id',
        'quantity',
        'unit_value',
        'subtotal',
    ];

    protected $casts = [
        'unit_value' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(ConsignmentReturn::class, 'consignment_return_id');
    }

    public function consignmentItem(): BelongsTo
    {
        return $this->belongsTo(ConsignmentItem::class);
    }
}
