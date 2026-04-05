<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantInvoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'amount',
        'currency',
        'billing_period_start',
        'billing_period_end',
        'status',
        'paid_at',
        'due_at',
        'payment_method',
        'transaction_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'paid_at' => 'datetime',
        'due_at' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(TenantPlan::class, 'plan_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_at && $this->due_at->isPast();
    }
}
