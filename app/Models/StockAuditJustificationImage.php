<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditJustificationImage extends Model
{
    protected $fillable = [
        'justification_id',
        'file_path',
        'file_name',
        'uploaded_by_user_id',
    ];

    public function justification(): BelongsTo
    {
        return $this->belongsTo(StockAuditStoreJustification::class, 'justification_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
