<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAuditStoreJustification extends Model
{
    protected $fillable = [
        'audit_id',
        'item_id',
        'justification_text',
        'found_quantity',
        'submitted_by_user_id',
        'submitted_at',
        'review_status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'found_quantity' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'audit_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockAuditItem::class, 'item_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(StockAuditJustificationImage::class, 'justification_id');
    }
}
