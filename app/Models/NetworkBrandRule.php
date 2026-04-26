<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regra de marca aceita por rede no contexto do matching de avariados.
 *
 * Comportamento (igual à v1):
 *  - Se uma rede tem ≥1 row aqui (com is_active=true): SOMENTE marcas listadas
 *    são aceitas em matches envolvendo lojas dessa rede
 *  - Se uma rede NÃO tem nenhum row ativo: aceita qualquer marca
 *
 * Validado bidirecionalmente em DamagedProductMatchingService.
 */
class NetworkBrandRule extends Model
{
    use Auditable;

    protected $fillable = [
        'network_id',
        'brand_cigam_code',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'network_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_cigam_code', 'cigam_code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }
}
