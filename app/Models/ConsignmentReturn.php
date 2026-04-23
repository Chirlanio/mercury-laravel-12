<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Evento de retorno parcial/total de uma consignação. Uma consignação
 * pode ter múltiplos retornos (cliente devolve parte em visitas
 * diferentes). Cada evento referencia uma NF de retorno do CIGAM
 * (movement_code=21) via snapshot composite.
 *
 * Imutável após criação — inserções novas criam um novo ConsignmentReturn
 * em vez de alterar o existente (simplifica auditoria e conciliação).
 */
class ConsignmentReturn extends Model
{
    use HasFactory;

    protected $table = 'consignment_returns';

    protected $fillable = [
        'consignment_id',
        'return_invoice_number',
        'return_date',
        'return_store_code',
        'returned_quantity',
        'returned_value',
        'movement_id',
        'reconciled_at',
        'notes',
        'registered_by_user_id',
    ];

    protected $casts = [
        'return_date' => 'date',
        'reconciled_at' => 'datetime',
        'returned_value' => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentReturnItem::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
