<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamagedProductStatusHistory extends Model
{
    protected $table = 'damaged_product_status_histories';

    protected $fillable = [
        'damaged_product_id',
        'from_status',
        'to_status',
        'note',
        'triggered_by_match_id',
        'actor_user_id',
    ];

    public function damagedProduct(): BelongsTo
    {
        return $this->belongsTo(DamagedProduct::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function triggeredByMatch(): BelongsTo
    {
        return $this->belongsTo(DamagedProductMatch::class, 'triggered_by_match_id');
    }
}
