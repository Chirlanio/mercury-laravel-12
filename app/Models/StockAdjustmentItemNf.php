<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItemNf extends Model
{
    protected $table = 'stock_adjustment_item_nfs';

    protected $fillable = [
        'stock_adjustment_id',
        'stock_adjustment_item_id',
        'nf_entrada',
        'nf_saida',
        'nf_entrada_serie',
        'nf_saida_serie',
        'nf_entrada_date',
        'nf_saida_date',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'nf_entrada_date' => 'date',
        'nf_saida_date' => 'date',
    ];

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockAdjustmentItem::class, 'stock_adjustment_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
