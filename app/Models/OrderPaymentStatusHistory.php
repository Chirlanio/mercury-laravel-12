<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'order_payment_status_history';

    protected $fillable = [
        'order_payment_id',
        'old_status',
        'new_status',
        'changed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function orderPayment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function getOldStatusLabelAttribute(): string
    {
        return OrderPayment::STATUS_LABELS[$this->old_status] ?? $this->old_status ?? 'Criação';
    }

    public function getNewStatusLabelAttribute(): string
    {
        return OrderPayment::STATUS_LABELS[$this->new_status] ?? $this->new_status;
    }
}
