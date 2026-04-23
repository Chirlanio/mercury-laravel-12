<?php

namespace App\Models;

use App\Enums\ConsignmentItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item de uma consignação — snapshot do produto enviado.
 *
 * FK NOT NULL em `products` (regra M8) garante que só é possível
 * cadastrar item de produto existente no catálogo. Os campos
 * `reference`, `barcode`, `size_label`, `description` são snapshots
 * congelados no cadastro (alterações posteriores no catálogo não
 * afetam retrospectivamente).
 *
 * Status é derivado pelas quantities via `refreshDerivedStatus()` —
 * nunca definido manualmente.
 */
class ConsignmentItem extends Model
{
    use HasFactory;

    protected $table = 'consignment_items';

    protected $fillable = [
        'consignment_id',
        'movement_id',
        'product_id',
        'product_variant_id',
        'reference',
        'barcode',
        'size_label',
        'size_cigam_code',
        'description',
        'quantity',
        'unit_value',
        'total_value',
        'returned_quantity',
        'sold_quantity',
        'lost_quantity',
        'status',
        'lost_reason',
    ];

    protected $casts = [
        'status' => ConsignmentItemStatus::class,
        'unit_value' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Derivação de status
    // ------------------------------------------------------------------

    /**
     * Recalcula status derivado a partir das quantities atuais. Chamada
     * a cada mutação (retorno, venda, shrinkage). Retorna o próprio item
     * para encadear; NÃO persiste automaticamente — caller controla o save.
     */
    public function refreshDerivedStatus(): self
    {
        $this->status = ConsignmentItemStatus::derive(
            (int) $this->quantity,
            (int) $this->returned_quantity,
            (int) $this->sold_quantity,
            (int) $this->lost_quantity,
        );

        return $this;
    }

    public function getPendingQuantityAttribute(): int
    {
        return max(
            0,
            (int) $this->quantity
                - (int) $this->returned_quantity
                - (int) $this->sold_quantity
                - (int) $this->lost_quantity
        );
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ConsignmentReturnItem::class);
    }
}
