<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use Auditable;

    protected $fillable = [
        'store_id',
        'employee_id',
        'status',
        'observation',
        'created_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'delete_reason',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public const STATUS_LABELS = [
        'pending' => 'Pendente',
        'under_analysis' => 'Em Análise',
        'awaiting_response' => 'Aguardando Resposta',
        'balance_transfer' => 'Transferência de Saldo',
        'adjusted' => 'Ajustado',
        'no_adjustment' => 'Sem Ajuste',
        'cancelled' => 'Cancelado',
    ];

    public const VALID_TRANSITIONS = [
        'pending' => ['under_analysis', 'adjusted', 'no_adjustment', 'cancelled'],
        'under_analysis' => ['awaiting_response', 'adjusted', 'no_adjustment', 'balance_transfer', 'cancelled'],
        'awaiting_response' => ['under_analysis', 'adjusted', 'cancelled'],
        'balance_transfer' => ['adjusted', 'cancelled'],
        'cancelled' => ['pending'], // admin only
        'adjusted' => [],
        'no_adjustment' => [],
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class)->orderBy('sort_order');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StockAdjustmentStatusHistory::class)->orderByDesc('created_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['adjusted', 'no_adjustment']);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->whereMonth('created_at', $month)
            ->whereYear('created_at', $year);
    }
}
