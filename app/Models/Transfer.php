<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use Auditable;

    protected $fillable = [
        'relocation_id',
        'origin_store_id',
        'destination_store_id',
        'invoice_number',
        'volumes_qty',
        'products_qty',
        'transfer_type',
        'status',
        'observations',
        'created_by_user_id',
        'driver_user_id',
        'receiver_name',
        'pickup_date',
        'pickup_time',
        'delivery_date',
        'delivery_time',
        'confirmed_at',
        'confirmed_by_user_id',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'delivery_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public const STATUS_LABELS = [
        'pending' => 'Pendente',
        'in_transit' => 'Em Rota',
        'delivered' => 'Entregue',
        'confirmed' => 'Confirmado',
        'cancelled' => 'Cancelado',
    ];

    public const TYPE_LABELS = [
        'transfer' => 'Transferência',
        'relocation' => 'Remanejo',
        'return' => 'Devolução',
        'exchange' => 'Troca',
        'damage_match' => 'Match de Avaria',
    ];

    public function relocation(): BelongsTo
    {
        return $this->belongsTo(Relocation::class);
    }

    public function originStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'origin_store_id');
    }

    public function destinationStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->transfer_type] ?? $this->transfer_type;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where(function ($q) use ($storeId) {
            $q->where('origin_store_id', $storeId)
                ->orWhere('destination_store_id', $storeId);
        });
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->whereMonth('created_at', $month)
            ->whereYear('created_at', $year);
    }
}
