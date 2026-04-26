<?php

namespace App\Models;

use App\Enums\DamageMatchStatus;
use App\Enums\DamageMatchType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Match candidato/concretizado entre 2 damaged_products complementares.
 *
 * Convenção: product_a_id < product_b_id (garantido pela engine ANTES do insert)
 * combinada com unique constraint impede duplicatas A↔B em qualquer ordem.
 */
class DamagedProductMatch extends Model
{
    use Auditable;

    protected $table = 'damaged_product_matches';

    protected $fillable = [
        'product_a_id',
        'product_b_id',
        'match_type',
        'match_score',
        'match_payload',
        'suggested_origin_store_id',
        'suggested_destination_store_id',
        'status',
        'transfer_id',
        'reject_reason',
        'responded_by_user_id',
        'responded_at',
        'notified_at',
        'resolved_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'match_score' => 0,
    ];

    protected $casts = [
        'match_type' => DamageMatchType::class,
        'status' => DamageMatchStatus::class,
        'match_score' => 'decimal:2',
        'match_payload' => 'array',
        'responded_at' => 'datetime',
        'notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function productA(): BelongsTo
    {
        return $this->belongsTo(DamagedProduct::class, 'product_a_id');
    }

    public function productB(): BelongsTo
    {
        return $this->belongsTo(DamagedProduct::class, 'product_b_id');
    }

    public function suggestedOriginStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'suggested_origin_store_id');
    }

    public function suggestedDestinationStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'suggested_destination_store_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }

    /**
     * Retorna o "outro lado" do match a partir de um damaged_product.
     */
    public function partnerOf(DamagedProduct $product): ?DamagedProduct
    {
        if ($product->id === $this->product_a_id) {
            return $this->productB;
        }
        if ($product->id === $this->product_b_id) {
            return $this->productA;
        }

        return null;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DamageMatchStatus::PENDING->value);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where(function ($q) use ($productId) {
            $q->where('product_a_id', $productId)
                ->orWhere('product_b_id', $productId);
        });
    }
}
