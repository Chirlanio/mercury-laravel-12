<?php

namespace App\Models;

use App\Enums\ReversalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReversalStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reversal_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'from_status' => ReversalStatus::class,
        'to_status' => ReversalStatus::class,
        'created_at' => 'datetime',
    ];

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(Reversal::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
