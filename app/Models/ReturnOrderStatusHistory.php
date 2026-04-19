<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnOrderStatusHistory extends Model
{
    protected $table = 'return_order_status_histories';

    public $timestamps = false;

    protected $fillable = [
        'return_order_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'from_status' => ReturnStatus::class,
        'to_status' => ReturnStatus::class,
        'created_at' => 'datetime',
    ];

    public function returnOrder(): BelongsTo
    {
        return $this->belongsTo(ReturnOrder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
