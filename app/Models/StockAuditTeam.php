<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAuditTeam extends Model
{
    protected $fillable = [
        'audit_id',
        'vendor_id',
        'user_id',
        'external_staff_name',
        'external_staff_document',
        'role',
        'is_third_party',
    ];

    protected $casts = [
        'is_third_party' => 'boolean',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'audit_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(StockAuditVendor::class, 'vendor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function areaAssignments(): HasMany
    {
        return $this->hasMany(StockAuditAreaAssignment::class, 'team_id');
    }
}
