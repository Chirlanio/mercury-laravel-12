<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'stock_adjustment_status_history';

    protected $fillable = [
        'stock_adjustment_id',
        'old_status',
        'new_status',
        'changed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
