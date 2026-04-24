<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerVipTier;
use App\Models\CustomerVipTierConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Serviço de classificação anual de clientes VIP (Black/Gold).
 *
 * Fluxo híbrido:
 *  1. generateSuggestions(year) — roda agregação sobre movements, aplica
 *     thresholds do ano (customer_vip_tier_configs) e upserta em
 *     customer_vip_tiers com source=auto. Respeita curadoria manual existente:
 *     se uma linha já tem curated_at preenchido, apenas atualiza os snapshots
 *     (total_revenue, total_orders) e NUNCA sobrescreve final_tier.
 *  2. curate(customer, year, tier, ...) — Marketing promove/rebaixa um cliente.
 *     Atualiza final_tier, preenche curated_at/curated_by_user_id, muda source
 *     para 'manual'.
 *  3. remove(customer, year) — tira o cliente da lista do ano (zera final_tier
 *     mas preserva o registro + snapshots para histórico).
 *
 * Regra de faturamento (alinhada com MovementController/CLAUDE.md):
 *   - movement_code = 2 → soma net_value (venda PDV/ecom)
 *   - movement_code = 6 AND entry_exit = 'E' → subtrai net_value (devolução)
 *   - Agrupado por cpf_customer, YEAR(movement_date) = $year
 *   - Exige CPF não nulo no cliente para vincular via customers.cpf
 */
class CustomerVipClassificationService
{
    /**
     * Roda a classificação automática. Retorna um resumo do processamento.
     *
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
        $revenueByCpf = $this->aggregateRevenueByCpf($year);

        $summary = [
            'year' => $year,
            'processed' => 0,
            'suggested_black' => 0,
            'suggested_gold' => 0,
            'below_threshold' => 0,
            'preserved_curated' => 0,
        ];

        if ($revenueByCpf->isEmpty()) {
            return $summary;
        }

        $cpfs = $revenueByCpf->pluck('cpf')->all();

        // Mapa cpf → customer (só registros com CPF preenchido batem)
        $customers = Customer::whereIn('cpf', $cpfs)->get()->keyBy('cpf');

        DB::transaction(function () use ($year, $revenueByCpf, $customers, $thresholds, &$summary) {
            $now = now();

            foreach ($revenueByCpf as $row) {
                $customer = $customers->get($row->cpf);
                if (! $customer) {
                    // CPF em movements que não tem match em customers — ignora
                    continue;
                }

                $summary['processed']++;

                $revenue = (float) $row->revenue;
                $orders = (int) $row->orders;
                $suggested = $this->tierForRevenue($revenue, $thresholds);

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
                    // Preserva curadoria — só atualiza snapshots + suggested_tier.
                    $existing->update([
                        'total_revenue' => $revenue,
                        'total_orders' => $orders,
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
                        'suggested_at' => $now,
                        'source' => CustomerVipTier::SOURCE_AUTO,
                    ],
                );
            }
        });

        return $summary;
    }

    /**
     * Curadoria manual: Marketing promove, rebaixa, ou define tier de um cliente.
     *
     * @param  string|null  $tier  'black'|'gold'|null (null remove da lista)
     */
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
            // Snapshots ficam intactos se já existirem; se for criação do zero
            // (cliente que Marketing quer classificar sem passar pelo auto),
            // zera por segurança — pode ser atualizado na próxima rodada.
            if (! $record->exists) {
                $record->total_revenue = 0;
                $record->total_orders = 0;
            }
            $record->save();

            return $record;
        });
    }

    /**
     * Remove cliente da lista VIP do ano. Preserva histórico (registro + snapshots
     * continuam) mas zera final_tier e marca como manualmente curado.
     */
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

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /**
     * Carrega thresholds configurados pro ano. Array com possíveis chaves
     * 'black' e 'gold' (uma, ambas ou nenhuma). Se não houver nada configurado,
     * a classificação roda mas nenhum cliente recebe tier sugerido.
     *
     * @return array<string,float>
     */
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
     * Agrega faturamento líquido e # de NFs por CPF para o ano.
     *
     * @return \Illuminate\Support\Collection<int, object{cpf: string, revenue: float, orders: int}>
     */
    private function aggregateRevenueByCpf(int $year): \Illuminate\Support\Collection
    {
        // MySQL/MariaDB: YEAR(movement_date). SQLite em testes: strftime.
        // Evitamos função de ano em índice deixando o BETWEEN fazer range scan.
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
}
