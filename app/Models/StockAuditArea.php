<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAuditArea extends Model
{
    protected $fillable = [
        'audit_id',
        'name',
        'barcode_label',
        'sort_order',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'audit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAuditItem::class, 'area_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StockAuditAreaAssignment::class, 'area_id');
    }
}
