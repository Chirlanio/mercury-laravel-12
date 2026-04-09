<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAuditVendor extends Model
{
    use Auditable;

    protected $fillable = [
        'company_name',
        'cnpj',
        'contact_name',
        'contact_phone',
        'contact_email',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(StockAudit::class, 'vendor_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(StockAuditTeam::class, 'vendor_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
