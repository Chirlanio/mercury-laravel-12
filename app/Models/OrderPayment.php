<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderPayment extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'store_id',
        'area_id',
        'supplier_name',
        'description',
        'total_value',
        'payment_type',
        'status',
        'number_nf',
        'launch_number',
        'due_date',
        'date_paid',
        'installments',
        'bank_name',
        'agency',
        'checking_account',
        'pix_key_type',
        'pix_key',
        'requested_by_user_id',
        'approved_by_user_id',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'due_date' => 'date',
        'date_paid' => 'date',
    ];

    public const STATUS_LABELS = [
        'backlog' => 'Solicitação',
        'doing' => 'Reg. Fiscal',
        'waiting' => 'Lançado',
        'done' => 'Pago',
    ];

    public const VALID_TRANSITIONS = [
        'backlog' => ['doing'],
        'doing' => ['backlog', 'waiting'],
        'waiting' => ['doing', 'done'],
        'done' => ['waiting'],
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'R$ ' . number_format($this->total_value, 2, ',', '.');
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', today())
            ->where('status', '!=', 'done');
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->whereMonth('created_at', $month)
            ->whereYear('created_at', $year);
    }
}
