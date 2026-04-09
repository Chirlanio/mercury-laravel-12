<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditAccuracyHistory extends Model
{
    protected $table = 'stock_audit_accuracy_history';

    protected $fillable = [
        'store_id',
        'audit_id',
        'accuracy_percentage',
        'total_items',
        'total_divergences',
        'financial_loss',
        'financial_surplus',
        'financial_loss_cost',
        'financial_surplus_cost',
        'audit_type',
        'audit_date',
    ];

    protected $casts = [
        'accuracy_percentage' => 'decimal:2',
        'financial_loss' => 'decimal:2',
        'financial_surplus' => 'decimal:2',
        'financial_loss_cost' => 'decimal:2',
        'financial_surplus_cost' => 'decimal:2',
        'audit_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'audit_id');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }
}
