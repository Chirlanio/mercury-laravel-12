<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Linha de um orçamento — 1 linha por (CC + AC + MC + store opcional) com
 * os 12 valores mensais e total calculado.
 *
 * Usa Auditable para registrar edições pontuais (Melhoria 8 do roadmap):
 * cada update gera uma linha em activity_logs com before/after, acessível
 * no audit trail do projeto.
 */
class BudgetItem extends Model
{
    use Auditable;
    protected $fillable = [
        'budget_upload_id',
        'accounting_class_id',
        'management_class_id',
        'cost_center_id',
        'store_id',
        'supplier',
        'justification',
        'account_description',
        'class_description',
        'month_01_value', 'month_02_value', 'month_03_value',
        'month_04_value', 'month_05_value', 'month_06_value',
        'month_07_value', 'month_08_value', 'month_09_value',
        'month_10_value', 'month_11_value', 'month_12_value',
        'year_total',
    ];

    protected $casts = [
        'month_01_value' => 'decimal:2',
        'month_02_value' => 'decimal:2',
        'month_03_value' => 'decimal:2',
        'month_04_value' => 'decimal:2',
        'month_05_value' => 'decimal:2',
        'month_06_value' => 'decimal:2',
        'month_07_value' => 'decimal:2',
        'month_08_value' => 'decimal:2',
        'month_09_value' => 'decimal:2',
        'month_10_value' => 'decimal:2',
        'month_11_value' => 'decimal:2',
        'month_12_value' => 'decimal:2',
        'year_total' => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function upload(): BelongsTo
    {
        return $this->belongsTo(BudgetUpload::class, 'budget_upload_id');
    }

    public function accountingClass(): BelongsTo
    {
        return $this->belongsTo(AccountingClass::class);
    }

    public function managementClass(): BelongsTo
    {
        return $this->belongsTo(ManagementClass::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * OrderPayments vinculadas a este item de orçamento — base do consumo
     * previsto × realizado.
     */
    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForUpload(Builder $query, int $uploadId): Builder
    {
        return $query->where('budget_upload_id', $uploadId);
    }

    public function scopeForCostCenter(Builder $query, int $costCenterId): Builder
    {
        return $query->where('cost_center_id', $costCenterId);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Recalcula `year_total` = soma dos 12 meses. Não persiste — chame
     * save() ou use no service durante criação.
     */
    public function computeYearTotal(): float
    {
        $sum = 0.0;
        for ($i = 1; $i <= 12; $i++) {
            $col = 'month_'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'_value';
            $sum += (float) $this->{$col};
        }

        return round($sum, 2);
    }

    /**
     * Retorna array [jan=>val, ..., dez=>val] em ordem.
     *
     * @return array<int, float>
     */
    public function monthlyValues(): array
    {
        $out = [];
        for ($i = 1; $i <= 12; $i++) {
            $col = 'month_'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'_value';
            $out[$i] = (float) $this->{$col};
        }

        return $out;
    }
}
