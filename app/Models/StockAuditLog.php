<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditLog extends Model
{
    protected $fillable = [
        'audit_id',
        'action_type',
        'old_status',
        'new_status',
        'changed_by_user_id',
        'notes',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'audit_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
