<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot imutável da matriz DRE em um fechamento. Usado para garantir
 * leitura idêntica de períodos fechados mesmo que mappings/lançamentos
 * retroativos sejam alterados.
 *
 * Gerado por `DrePeriodClosingService::close()` — 1 linha por (fechamento,
 * escopo Geral|Rede|Loja, scope_id, linha DRE, year_month).
 *
 * Apagado em cascata ao deletar o fechamento ou em `reopen()`.
 */
class DrePeriodClosingSnapshot extends Model
{
    use HasFactory;

    protected $table = 'dre_period_closing_snapshots';

    public const SCOPE_GENERAL = 'GENERAL';
    public const SCOPE_NETWORK = 'NETWORK';
    public const SCOPE_STORE = 'STORE';

    protected $fillable = [
        'dre_period_closing_id',
        'scope',
        'scope_id',
        'dre_management_line_id',
        'year_month',
        'actual_amount',
        'budget_amount',
    ];

    protected $casts = [
        'actual_amount' => 'decimal:2',
        'budget_amount' => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function periodClosing(): BelongsTo
    {
        return $this->belongsTo(DrePeriodClosing::class, 'dre_period_closing_id');
    }

    public function dreManagementLine(): BelongsTo
    {
        return $this->belongsTo(DreManagementLine::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForClosing(Builder $query, int $closingId): Builder
    {
        return $query->where('dre_period_closing_id', $closingId);
    }

    public function scopeForScope(Builder $query, string $scope, ?int $scopeId = null): Builder
    {
        $query->where('scope', $scope);
        if ($scopeId !== null) {
            $query->where('scope_id', $scopeId);
        }

        return $query;
    }

    public function scopeForYearMonth(Builder $query, string $yearMonth): Builder
    {
        return $query->where('year_month', $yearMonth);
    }
}
