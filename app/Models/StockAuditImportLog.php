<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditImportLog extends Model
{
    protected $fillable = [
        'audit_id',
        'count_round',
        'area_id',
        'file_name',
        'format_type',
        'uploaded_by_user_id',
        'total_rows',
        'success_rows',
        'error_rows',
        'rejected_csv_path',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'audit_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(StockAuditArea::class, 'area_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
