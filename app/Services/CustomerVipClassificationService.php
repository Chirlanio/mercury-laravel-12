<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerVipTier;
use App\Models\CustomerVipTierConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Serviço de classificação anual de clientes VIP — programa MS Life.
 *
 * Regra do programa:
 *  - Apenas vendas em lojas da rede MEIA SOLA contam (outras redes do grupo,
 *    como AREZZO, SCHUTZ, MS OFF, são EXCLUÍDAS). O objetivo é promover a
 *    marca Meia Sola especificamente.
 *  - Faturamento líquido = SUM(movements.net_value) com:
 *      movement_code = 2 → soma (venda)
 *      movement_code = 6 AND entry_exit = 'E' → subtrai (devolução)
 *    agrupado por cpf_customer + restrito a store_code em lojas Meia Sola.
 *  - Loja de preferência: a de maior faturamento desse cliente; em empate,
 *    maior número de NFs (tickets); em empate, maior quantidade de itens.
 *
 * Fluxo híbrido:
 *  1. generateSuggestions(year) — roda agregação, aplica thresholds do ano,
 *     upserta em customer_vip_tiers com source=auto. Curadorias manuais
 *     (curated_at preenchido) são preservadas — só snapshots são refrescados.
 *  2. curate(...) — Marketing define final_tier com source=manual.
 *  3. remove(...) — zera final_tier preservando histórico.
 */
class CustomerVipClassificationService
{
    /** Nome da rede cujas lojas fazem parte do programa MS Life. */
    public const MS_LIFE_NETWORK_NAME = 'MEIA SOLA';

    /**
     * @return array{
     *     year: int,
     *     processed: int,
     *     suggested_black: int,
     *     suggested_gold: int,
     *     below_threshold: int,
     *     preserved_curated: int
     * }
     */
    public function generateSuggestions(int $year): array
    {
        $thresholds = $this->loadThresholds($year);
        $storeCodes = $this->msLifeStoreCodes();

        $summary = [
            'year' => $year,
            'processed' => 0,
            'suggested_black' => 0,
            'suggested_gold' => 0,
            'below_threshold' => 0,
            'preserved_curated' => 0,
        ];

        if (empty($storeCodes)) {
            return $summary; // rede Meia Sola não tem lojas cadastradas — nada a fazer
        }

        $revenueByCpf = $this->aggregateRevenueByCpf($year, $storeCodes);
        if ($revenueByCpf->isEmpty()) {
            return $summary;
        }

        $cpfs = $revenueByCpf->pluck('cpf')->all();
        $customers = Customer::whereIn('cpf', $cpfs)->get()->keyBy('cpf');

        // Loja preferida por CPF (entre as lojas Meia Sola)
        $preferredStoreByCpf = $this->resolvePreferredStores($year, $storeCodes, $cpfs);

        DB::transaction(function () use ($year, $revenueByCpf, $customers, $thresholds, $preferredStoreByCpf, &$summary) {
            $now = now();

            foreach ($revenueByCpf as $row) {
                $customer = $customers->get($row->cpf);
                if (! $customer) {
                    continue;
                }

                $summary['processed']++;

                $revenue = (float) $row->revenue;
                $orders = (int) $row->orders;
                $suggested = $this->tierForRevenue($revenue, $thresholds);
                $preferredStore = $preferredStoreByCpf[$row->cpf] ?? null;

                if ($suggested === CustomerVipTier::TIER_BLACK) {
                    $summary['suggested_black']++;
                } elseif ($suggested === CustomerVipTier::TIER_GOLD) {
                    $summary['suggested_gold']++;
                } else {
                    $summary['below_threshold']++;
                }

                $existing = CustomerVipTier::where('customer_id', $customer->id)
                    ->where('year', $year)
                    ->first();

                if ($existing && $existing->curated_at !== null) {
                    // Preserva curadoria — atualiza snapshots + suggested_tier.
                    $existing->update([
                        'total_revenue' => $revenue,
                        'total_orders' => $orders,
                        'preferred_store_code' => $preferredStore,
                        'suggested_tier' => $suggested,
                        'suggested_at' => $now,
                    ]);
                    $summary['preserved_curated']++;

                    continue;
                }

                CustomerVipTier::updateOrCreate(
                    ['customer_id' => $customer->id, 'year' => $year],
                    [
                        'suggested_tier' => $suggested,
                        'final_tier' => $suggested,
                        'total_revenue' => $revenue,
                        'total_orders' => $orders,
                        'preferred_store_code' => $preferredStore,
                        'suggested_at' => $now,
                        'source' => CustomerVipTier::SOURCE_AUTO,
                    ],
                );
            }
        });

        return $summary;
    }

    public function curate(Customer $customer, int $year, ?string $tier, ?string $notes, User $by): CustomerVipTier
    {
        if ($tier !== null && ! in_array($tier, [CustomerVipTier::TIER_BLACK, CustomerVipTier::TIER_GOLD], true)) {
            throw new \InvalidArgumentException("Tier inválido: {$tier}");
        }

        return DB::transaction(function () use ($customer, $year, $tier, $notes, $by) {
            $record = CustomerVipTier::firstOrNew([
                'customer_id' => $customer->id,
                'year' => $year,
            ]);

            $record->final_tier = $tier;
            $record->curated_at = now();
            $record->curated_by_user_id = $by->id;
            $record->source = CustomerVipTier::SOURCE_MANUAL;
            if ($notes !== null) {
                $record->notes = $notes;
            }
            if (! $record->exists) {
                $record->total_revenue = 0;
                $record->total_orders = 0;
            }
            $record->save();

            return $record;
        });
    }

    public function remove(Customer $customer, int $year, User $by): ?CustomerVipTier
    {
        $record = CustomerVipTier::where('customer_id', $customer->id)
            ->where('year', $year)
            ->first();

        if (! $record) {
            return null;
        }

        $record->update([
            'final_tier' => null,
            'curated_at' => now(),
            'curated_by_user_id' => $by->id,
            'source' => CustomerVipTier::SOURCE_MANUAL,
        ]);

        return $record->fresh();
    }

    /**
     * Retorna a lista de store_codes que pertencem à rede MEIA SOLA.
     * Cacheado em memória por request — tabela stores raramente muda.
     *
     * @return array<int, string>
     */
    public function msLifeStoreCodes(): array
    {
        return Cache::store('array')->rememberForever('vip.ms_life_store_codes', function () {
            // Comparação case-insensitive — o seeder de produção usa 'MEIA SOLA'
            // (uppercase), mas TestHelpers usa 'Meia Sola' para consistência
            // com a fixture de outros módulos. Ambos são válidos.
            return DB::table('stores')
                ->join('networks', 'stores.network_id', '=', 'networks.id')
                ->whereRaw('UPPER(networks.nome) = ?', [self::MS_LIFE_NETWORK_NAME])
                ->pluck('stores.code')
                ->all();
        });
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function loadThresholds(int $year): array
    {
        return CustomerVipTierConfig::forYear($year)
            ->get()
            ->mapWithKeys(fn ($c) => [$c->tier => (float) $c->min_revenue])
            ->toArray();
    }

    private function tierForRevenue(float $revenue, array $thresholds): ?string
    {
        $black = $thresholds[CustomerVipTier::TIER_BLACK] ?? null;
        $gold = $thresholds[CustomerVipTier::TIER_GOLD] ?? null;

        if ($black !== null && $revenue >= $black) {
            return CustomerVipTier::TIER_BLACK;
        }
        if ($gold !== null && $revenue >= $gold) {
            return CustomerVipTier::TIER_GOLD;
        }

        return null;
    }

    /**
     * Agrega faturamento líquido + NFs por CPF no ano, restrito a lojas MS Life.
     *
     * @param  array<int, string>  $storeCodes
     * @return \Illuminate\Support\Collection<int, object{cpf: string, revenue: float, orders: int}>
     */
    private function aggregateRevenueByCpf(int $year, array $storeCodes): \Illuminate\Support\Collection
    {
        $start = sprintf('%d-01-01', $year);
        $end = sprintf('%d-12-31', $year);

        return DB::table('movements')
            ->select([
                'cpf_customer as cpf',
                DB::raw(
                    'SUM(CASE '
                    ."WHEN movement_code = 2 THEN net_value "
                    ."WHEN movement_code = 6 AND entry_exit = 'E' THEN -net_value "
                    .'ELSE 0 END) as revenue'
                ),
                DB::raw('COUNT(DISTINCT invoice_number) as orders'),
            ])
            ->whereNotNull('cpf_customer')
            ->where('cpf_customer', '!=', '')
            ->whereIn('store_code', $storeCodes)
            ->whereBetween('movement_date', [$start, $end])
            ->where(function ($q) {
                $q->where('movement_code', 2)
                    ->orWhere(function ($qq) {
                        $qq->where('movement_code', 6)->where('entry_exit', 'E');
                    });
            })
            ->groupBy('cpf_customer')
            ->havingRaw('SUM(CASE WHEN movement_code = 2 THEN net_value '
                ."WHEN movement_code = 6 AND entry_exit = 'E' THEN -net_value "
                .'ELSE 0 END) > 0')
            ->orderByDesc('revenue')
            ->get();
    }

    /**
     * Descobre a loja preferida de cada cliente entre as lojas Meia Sola.
     *
     * Critério (hierárquico, tie-breaking):
     *   1. Maior faturamento líquido
     *   2. Maior número de NFs (tickets)
     *   3. Maior quantidade de itens (soma de quantity respeitando código)
     *
     * @param  array<int, string>  $storeCodes
     * @param  array<int, string>  $cpfs
     * @return array<string, string>  cpf → store_code
     */
    private function resolvePreferredStores(int $year, array $storeCodes, array $cpfs): array
    {
        if (empty($cpfs)) {
            return [];
        }

        $start = sprintf('%d-01-01', $year);
        $end = sprintf('%d-12-31', $year);

        $rows = DB::table('movements')
            ->select([
                'cpf_customer as cpf',
                'store_code',
                DB::raw(
                    'SUM(CASE '
                    ."WHEN movement_code = 2 THEN net_value "
                    ."WHEN movement_code = 6 AND entry_exit = 'E' THEN -net_value "
                    .'ELSE 0 END) as revenue'
                ),
                DB::raw('COUNT(DISTINCT invoice_number) as tickets'),
                DB::raw(
                    'SUM(CASE '
                    ."WHEN movement_code = 2 THEN quantity "
                    ."WHEN movement_code = 6 AND entry_exit = 'E' THEN -quantity "
                    .'ELSE 0 END) as items'
                ),
            ])
            ->whereIn('cpf_customer', $cpfs)
            ->whereIn('store_code', $storeCodes)
            ->whereBetween('movement_date', [$start, $end])
            ->where(function ($q) {
                $q->where('movement_code', 2)
                    ->orWhere(function ($qq) {
                        $qq->where('movement_code', 6)->where('entry_exit', 'E');
                    });
            })
            ->groupBy('cpf_customer', 'store_code')
            ->get();

        // Para cada CPF, escolhe a loja vencedora pelo tie-breaking.
        $preferred = [];
        foreach ($rows as $row) {
            $cpf = $row->cpf;
            $candidate = [
                'store' => $row->store_code,
                'revenue' => (float) $row->revenue,
                'tickets' => (int) $row->tickets,
                'items' => (float) $row->items,
            ];

            if (! isset($preferred[$cpf])) {
                $preferred[$cpf] = $candidate;

                continue;
            }

            $current = $preferred[$cpf];
            if ($this->isBetterCandidate($candidate, $current)) {
                $preferred[$cpf] = $candidate;
            }
        }

        return array_map(fn ($p) => $p['store'], $preferred);
    }

    private function isBetterCandidate(array $a, array $b): bool
    {
        if ($a['revenue'] !== $b['revenue']) {
            return $a['revenue'] > $b['revenue'];
        }
        if ($a['tickets'] !== $b['tickets']) {
            return $a['tickets'] > $b['tickets'];
        }
        if ($a['items'] !== $b['items']) {
            return $a['items'] > $b['items'];
        }
        // Empate técnico — menor store_code vence (estável entre rodadas)
        return strcmp($a['store'], $b['store']) < 0;
    }
}
