<?php

namespace App\Services\DRE;

use App\Models\DreManagementLine;
use App\Models\DreMapping;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Resolve `(account_id, cost_center_id, date) → management_line_id` aplicando a
 * regra de precedência do `docs/dre-arquitetura.md §4.3`:
 *
 *   1. Match ESPECÍFICO — mapping com `cost_center_id` igual ao fornecido e
 *      vigência cobrindo a data.
 *   2. Match CORINGA — mapping com `cost_center_id = NULL` e vigência cobrindo.
 *   3. Fallback `L99_UNCLASSIFIED` — nada some silenciosamente.
 *
 * A resolução em PHP (Opção B do §4.2 arquitetura) mantém a lógica isolada e
 * 100% unit-testável, independente de recurso SQL específico.
 *
 * Cria-se em memória uma vez com `loadForPeriod` (poucas centenas de mappings
 * ativos em produção cabem em RAM). O resolve é O(1) amortizado por conta
 * via índice pré-construído.
 */
class DreMappingResolver
{
    /** Cache estático do id da linha-fantasma — lookup único por request. */
    private static ?int $unclassifiedLineIdCache = null;

    /**
     * @var array<int, array<int, array{cost_center_id: ?int, line_id: int, effective_from: string, effective_to: ?string}>>
     *   Indexado por `account_id`. Cada entrada é um array de candidatos.
     */
    private array $index = [];

    /**
     * @param  array<int, array{chart_of_account_id: int, cost_center_id: ?int, dre_management_line_id: int, effective_from: string, effective_to: ?string}>  $mappings
     */
    public function __construct(array $mappings)
    {
        foreach ($mappings as $m) {
            $accountId = (int) $m['chart_of_account_id'];
            $this->index[$accountId] ??= [];
            $this->index[$accountId][] = [
                'cost_center_id' => $m['cost_center_id'] !== null ? (int) $m['cost_center_id'] : null,
                'line_id' => (int) $m['dre_management_line_id'],
                'effective_from' => (string) $m['effective_from'],
                'effective_to' => $m['effective_to'] !== null ? (string) $m['effective_to'] : null,
            ];
        }
    }

    /**
     * Carrega todos os mappings ativos cuja vigência intersecta com o período
     * `$from..$to`. Criterio conservador — mapping cujo `effective_from <= to`
     * e (`effective_to IS NULL OR effective_to >= from`) pode ser vigente em
     * algum ponto do período.
     */
    public static function loadForPeriod(CarbonInterface $from, CarbonInterface $to): self
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $rows = DreMapping::query()
            ->whereNull('deleted_at')
            ->where('effective_from', '<=', $toStr)
            ->where(function ($q) use ($fromStr) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $fromStr);
            })
            ->get(['chart_of_account_id', 'cost_center_id', 'dre_management_line_id', 'effective_from', 'effective_to'])
            ->map(fn ($r) => [
                'chart_of_account_id' => $r->chart_of_account_id,
                'cost_center_id' => $r->cost_center_id,
                'dre_management_line_id' => $r->dre_management_line_id,
                'effective_from' => $r->effective_from->format('Y-m-d'),
                'effective_to' => $r->effective_to?->format('Y-m-d'),
            ])
            ->all();

        return new self($rows);
    }

    /**
     * Resolve a linha da DRE para uma tupla `(account_id, cost_center_id, date)`.
     * Nunca lança — fallback é a linha-fantasma `L99_UNCLASSIFIED`.
     */
    public function resolve(int $accountId, ?int $costCenterId, CarbonInterface|DateTimeInterface|string $date): int
    {
        $dateStr = $this->dateString($date);
        $candidates = $this->index[$accountId] ?? [];

        // 1. Específico (cost_center_id bate).
        foreach ($candidates as $c) {
            if ($c['cost_center_id'] === $costCenterId
                && $this->isActiveOn($c, $dateStr)
                && $costCenterId !== null) {
                return $c['line_id'];
            }
        }

        // 2. Coringa (cost_center_id null).
        foreach ($candidates as $c) {
            if ($c['cost_center_id'] === null && $this->isActiveOn($c, $dateStr)) {
                return $c['line_id'];
            }
        }

        // 3. Fallback — linha-fantasma.
        return self::unclassifiedLineId();
    }

    /**
     * Batch lookup — resolve N tuplas mantendo ordem. Mesma semântica do
     * `resolve()` mas reaproveita a localização do candidato por conta.
     *
     * @param  array<int, array{account_id: int, cost_center_id: ?int, date: string}>  $tuples
     * @return array<int, int>
     */
    public function resolveMany(array $tuples): array
    {
        $results = [];
        foreach ($tuples as $i => $t) {
            $results[$i] = $this->resolve(
                (int) $t['account_id'],
                $t['cost_center_id'] !== null ? (int) $t['cost_center_id'] : null,
                (string) $t['date'],
            );
        }

        return $results;
    }

    /**
     * Id da linha-fantasma (`L99_UNCLASSIFIED`). Cachado estaticamente por
     * request para não rodar query N vezes.
     */
    public static function unclassifiedLineId(): int
    {
        if (self::$unclassifiedLineIdCache !== null) {
            return self::$unclassifiedLineIdCache;
        }

        $line = DreManagementLine::query()
            ->where('code', DreManagementLine::UNCLASSIFIED_CODE)
            ->first(['id']);

        if (! $line) {
            throw new \RuntimeException(
                'Linha-fantasma L99_UNCLASSIFIED ausente em dre_management_lines. '
                .'Rode a migration `2026_04_22_400001_seed_unclassified_dre_management_line` '
                .'ou `php artisan tenants:migrate`.'
            );
        }

        return self::$unclassifiedLineIdCache = (int) $line->id;
    }

    /**
     * Apenas para tests — reseta o cache estático entre cenários.
     */
    public static function resetCache(): void
    {
        self::$unclassifiedLineIdCache = null;
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    /** @param array{effective_from:string, effective_to:?string} $c */
    private function isActiveOn(array $c, string $dateStr): bool
    {
        if ($c['effective_from'] > $dateStr) {
            return false;
        }

        if ($c['effective_to'] !== null && $c['effective_to'] < $dateStr) {
            return false;
        }

        return true;
    }

    private function dateString(CarbonInterface|DateTimeInterface|string $date): string
    {
        if (is_string($date)) {
            return substr($date, 0, 10);
        }

        return $date->format('Y-m-d');
    }
}
