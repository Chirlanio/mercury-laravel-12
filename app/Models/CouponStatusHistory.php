<?php

namespace App\Models;

use App\Enums\CouponStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponStatusHistory extends Model
{
    protected $table = 'coupon_status_histories';

    public $timestamps = false;

    protected $fillable = [
        'coupon_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'from_status' => CouponStatus::class,
        'to_status' => CouponStatus::class,
        'created_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
