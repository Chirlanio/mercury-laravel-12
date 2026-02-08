<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Sale extends Model
{
    use Auditable;

    protected $fillable = [
        'store_id',
        'employee_id',
        'date_sales',
        'total_sales',
        'qtde_total',
        'user_hash',
        'source',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'date_sales' => 'date',
        'total_sales' => 'decimal:2',
        'qtde_total' => 'integer',
        'store_id' => 'integer',
        'employee_id' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->whereMonth('date_sales', $month)->whereYear('date_sales', $year);
    }

    public function scopeForDateRange(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('date_sales', [$start, $end]);
    }

    public function scopeFromCigam(Builder $query): Builder
    {
        return $query->where('source', 'cigam');
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('source', 'manual');
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'R$ ' . number_format((float) $this->total_sales, 2, ',', '.');
    }
}
