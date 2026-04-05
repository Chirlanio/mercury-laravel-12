<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentInstallment extends Model
{
    protected $table = 'order_payment_installments';

    protected $fillable = [
        'order_payment_id',
        'installment_number',
        'installment_value',
        'date_payment',
        'is_paid',
        'date_paid',
        'paid_by_user_id',
        'notes',
    ];

    protected $casts = [
        'installment_value' => 'decimal:2',
        'date_payment' => 'date',
        'date_paid' => 'date',
        'is_paid' => 'boolean',
    ];

    public function orderPayment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function getFormattedValueAttribute(): string
    {
        return 'R$ ' . number_format($this->installment_value, 2, ',', '.');
    }

    public function getIsOverdueAttribute(): bool
    {
        return !$this->is_paid && $this->date_payment && $this->date_payment->isPast();
    }
}
