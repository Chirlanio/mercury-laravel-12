<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfirmedSale extends Model
{
    protected $fillable = [
        'employee_id',
        'store_id',
        'sale_value',
        'reference_month',
        'reference_year',
        'confirmed_by_user_id',
    ];

    protected $casts = [
        'sale_value' => 'decimal:2',
        'reference_month' => 'integer',
        'reference_year' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->where('reference_month', $month)->where('reference_year', $year);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }
}
