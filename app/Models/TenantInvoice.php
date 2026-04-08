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
        'billing_cycle',
        'billing_period_start',
        'billing_period_end',
        'status',
        'paid_at',
        'due_at',
        'payment_method',
        'transaction_id',
        'gateway_provider',
        'gateway_id',
        'payment_url',
        'auto_generated',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'paid_at' => 'datetime',
        'due_at' => 'date',
        'auto_generated' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(TenantPlan::class, 'plan_id');
    }

    // Status checks

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue'
            || ($this->status === 'pending' && $this->due_at && $this->due_at->isPast());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // Status transitions

    public function markAsPaid(string $paymentMethod, ?string $transactionId = null, ?string $paidAt = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => $paidAt ?? now(),
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
        ]);
    }

    public function markAsOverdue(): void
    {
        $this->update(['status' => 'overdue']);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->where('billing_period_start', '>=', $start)
            ->where('billing_period_end', '<=', $end);
    }
}
