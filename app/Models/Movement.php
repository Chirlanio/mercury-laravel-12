<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movement extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_date', 'movement_time', 'store_code', 'cpf_customer',
        'invoice_number', 'movement_code', 'cpf_consultant', 'ref_size',
        'barcode', 'sale_price', 'cost_price', 'realized_value',
        'discount_value', 'quantity', 'entry_exit', 'net_value',
        'net_quantity', 'sync_batch_id', 'synced_at',
    ];

    protected $casts = [
        'movement_date' => 'date:Y-m-d',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'realized_value' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'quantity' => 'decimal:3',
        'net_value' => 'decimal:2',
        'net_quantity' => 'decimal:3',
        'movement_code' => 'integer',
        'synced_at' => 'datetime',
    ];

    // Relationships

    public function movementType(): BelongsTo
    {
        return $this->belongsTo(MovementType::class, 'movement_code', 'code');
    }

    public function reversalItems(): HasMany
    {
        return $this->hasMany(ReversalItem::class);
    }

    public function returnOrderItems(): HasMany
    {
        return $this->hasMany(ReturnOrderItem::class);
    }

    // Scopes

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('movement_date', $date);
    }

    public function scopeForDateRange(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('movement_date', [$start, $end]);
    }

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_code', $storeCode);
    }

    public function scopeForMovementCode(Builder $query, int $code): Builder
    {
        return $query->where('movement_code', $code);
    }

    public function scopeForConsultant(Builder $query, string $cpf): Builder
    {
        return $query->where('cpf_consultant', $cpf);
    }

    public function scopeSales(Builder $query): Builder
    {
        return $query->where('movement_code', 2);
    }

    public function scopeReturns(Builder $query): Builder
    {
        return $query->where('movement_code', 6)->where('entry_exit', 'E');
    }

    public function scopeSalesAndReturns(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('movement_code', 2)
              ->orWhere(function (Builder $q2) {
                  $q2->where('movement_code', 6)->where('entry_exit', 'E');
              });
        });
    }

    // Helpers

    public static function calculateNetValues(float $realizedValue, float $quantity, int $movementCode, string $entryExit): array
    {
        $netValue = ($movementCode === 6 && strtoupper($entryExit) === 'E')
            ? -abs($realizedValue)
            : abs($realizedValue);

        $netQuantity = (strtoupper($entryExit) === 'S')
            ? -abs($quantity)
            : abs($quantity);

        return [$netValue, $netQuantity];
    }
}
