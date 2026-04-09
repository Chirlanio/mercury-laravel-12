<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditAreaAssignment extends Model
{
    protected $fillable = [
        'area_id',
        'team_id',
        'count_round',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(StockAuditArea::class, 'area_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(StockAuditTeam::class, 'team_id');
    }
}
