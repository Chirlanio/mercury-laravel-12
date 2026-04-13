<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentAttachment extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
