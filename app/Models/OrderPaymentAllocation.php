<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentAllocation extends Model
{
    protected $table = 'order_payment_allocations';

    protected $fillable = [
        'order_payment_id',
        'cost_center_id',
        'store_id',
        'allocation_percentage',
        'allocation_value',
        'notes',
    ];

    protected $casts = [
        'allocation_percentage' => 'decimal:2',
        'allocation_value' => 'decimal:2',
    ];

    public function orderPayment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
