<?php

namespace App\Models;

use App\Traits\InvalidatesDreCacheOnChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Lançamento realizado. Espelho canônico unificado, alimentado por 3 fontes:
 *
 *   - ORDER_PAYMENT: observer projeta quando OrderPayment.status → done.
 *   - SALE: observer projeta na criação da venda (conta configurada por loja).
 *   - MANUAL_IMPORT / CIGAM_BALANCE: importador de balancete.
 *
 * `amount` é sinalizado: receita positiva, despesa negativa. Conversão é
 * responsabilidade dos projetores/importador conforme `account_group` da
 * conta contábil.
 *
 * `source_type` + `source_id` polimórficos permitem drill-through até a
 * origem (ex: da célula da matriz DRE para o OrderPayment específico).
 *
 * Sem soft delete — fonte canônica é imutável. Cancelamento de OP → remove
 * linha via observer; estorno contábil → linha nova, não edita antiga.
 */
class DreActual extends Model
{
    use HasFactory, InvalidatesDreCacheOnChange;

    protected $table = 'dre_actuals';

    public const SOURCE_ORDER_PAYMENT = 'ORDER_PAYMENT';
    public const SOURCE_SALE = 'SALE';
    public const SOURCE_MANUAL_IMPORT = 'MANUAL_IMPORT';
    public const SOURCE_CIGAM_BALANCE = 'CIGAM_BALANCE';

    protected $fillable = [
        'entry_date',
        'chart_of_account_id',
        'cost_center_id',
        'store_id',
        'amount',
        'source',
        'source_type',
        'source_id',
        'document',
        'description',
        'external_id',
        'reported_in_closed_period',
        'imported_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'reported_in_closed_period' => 'boolean',
        'imported_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sourceModel(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    /**
     * Alias semântico de `scopeBetween` pedido pelo prompt #2.
     * Aceita strings ISO ou instâncias Carbon.
     */
    public function scopeForPeriod(Builder $query, string|\DateTimeInterface $from, string|\DateTimeInterface $to): Builder
    {
        $fromStr = $from instanceof \DateTimeInterface ? $from->format('Y-m-d') : $from;
        $toStr = $to instanceof \DateTimeInterface ? $to->format('Y-m-d') : $to;

        return $query->whereBetween('entry_date', [$fromStr, $toStr]);
    }

    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeForStores(Builder $query, array $storeIds): Builder
    {
        return $query->whereIn('store_id', $storeIds);
    }

    /**
     * Filtro por unidade (loja). Aceita int, array ou Store model.
     * Nomenclatura "Unit" alinha com linguagem de DRE (unidade de negócio).
     */
    public function scopeForUnit(Builder $query, int|array|Store $unit): Builder
    {
        if ($unit instanceof Store) {
            return $query->where('store_id', $unit->id);
        }

        if (is_array($unit)) {
            return $query->whereIn('store_id', $unit);
        }

        return $query->where('store_id', $unit);
    }

    public function scopeForCostCenter(Builder $query, int|CostCenter $costCenter): Builder
    {
        $id = $costCenter instanceof CostCenter ? $costCenter->id : $costCenter;

        return $query->where('cost_center_id', $id);
    }

    public function scopeReportedInClosedPeriod(Builder $query): Builder
    {
        return $query->where('reported_in_closed_period', true);
    }
}
