<?php

namespace App\Services\DRE;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\DreMapping;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD do de-para conta contábil → linha gerencial da DRE.
 *
 * O `list()` retorna **contas contábeis analíticas de resultado** (grupos
 * 3, 4, 5) com seu mapping vigente embutido — é a view que a UI central
 * precisa (uma linha por conta, mapping como atributo). A tabela
 * `dre_mappings` em si é manipulada diretamente em create/update/delete/
 * bulkAssign.
 *
 * Parâmetros temporais usam data 'Y-m-d' (ou null = hoje) para pegar o
 * mapping vigente — coerente com §4.3 do plano arquitetural.
 */
class DreMappingService
{
    /**
     * Lista paginada de contas analíticas de resultado com o mapping
     * vigente na data passada.
     *
     * @return LengthAwarePaginator<int, ChartOfAccount>
     */
    public function list(DreMappingListFilter $filter): LengthAwarePaginator
    {
        $effectiveOn = $filter->effectiveOn ?: now()->toDateString();

        $query = ChartOfAccount::query()
            ->whereNull('chart_of_accounts.deleted_at')
            ->where(function (Builder $q) {
                // Prioriza `type` (prompt #2) com fallback em accepts_entries (legacy).
                $q->where('chart_of_accounts.type', AccountType::ANALYTICAL->value)
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('chart_of_accounts.type')
                            ->where('chart_of_accounts.accepts_entries', true);
                    });
            })
            ->whereIn('chart_of_accounts.account_group', [3, 4, 5])
            ->with(['defaultManagementClass:id,code,name'])
            ->orderBy('chart_of_accounts.code');

        // Subquery para anexar o mapping vigente (apenas um por conta,
        // preferindo specific CC quando o filtro pede CC; caso contrário,
        // pega o primeiro match em ordem de (cc specific > cc null).
        $query->addSelect('chart_of_accounts.*');

        $query->leftJoin('dre_mappings as m_active', function ($join) use ($effectiveOn, $filter) {
            $join->on('m_active.chart_of_account_id', '=', 'chart_of_accounts.id')
                ->whereNull('m_active.deleted_at')
                ->where('m_active.effective_from', '<=', $effectiveOn)
                ->where(function ($w) use ($effectiveOn) {
                    $w->whereNull('m_active.effective_to')
                        ->orWhere('m_active.effective_to', '>=', $effectiveOn);
                });
            if ($filter->costCenterId !== null) {
                // Prefere mapping específico para o CC filtrado.
                $join->where(function ($inner) use ($filter) {
                    $inner->where('m_active.cost_center_id', $filter->costCenterId)
                        ->orWhereNull('m_active.cost_center_id');
                });
            }
        });
        $query->addSelect([
            'active_mapping_id' => 'm_active.id',
            'active_mapping_cost_center_id' => 'm_active.cost_center_id',
            'active_mapping_line_id' => 'm_active.dre_management_line_id',
        ]);

        if ($filter->search) {
            $like = '%'.$filter->search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('chart_of_accounts.code', 'like', $like)
                    ->orWhere('chart_of_accounts.reduced_code', 'like', $like)
                    ->orWhere('chart_of_accounts.name', 'like', $like);
            });
        }

        if ($filter->accountGroup !== null) {
            $query->where('chart_of_accounts.account_group', $filter->accountGroup);
        }

        if ($filter->managementLineId !== null) {
            $query->where('m_active.dre_management_line_id', $filter->managementLineId);
        }

        if ($filter->onlyUnmapped) {
            $query->whereNull('m_active.id');
        }

        return $query->paginate($filter->perPage)->withQueryString();
    }

    public function create(array $data): DreMapping
    {
        $this->guardAnalyticalOnly((int) $data['chart_of_account_id']);
        $this->guardAgainstClosedPeriod(
            effectiveFrom: (string) $data['effective_from'],
            effectiveTo: $data['effective_to'] ?? null,
        );

        $this->guardNoTemporalOverlap(
            chartOfAccountId: (int) $data['chart_of_account_id'],
            costCenterId: $data['cost_center_id'] ?? null,
            effectiveFrom: $data['effective_from'],
            effectiveTo: $data['effective_to'] ?? null,
            excludingId: null,
        );

        return DB::transaction(fn () => DreMapping::create($this->sanitize($data)));
    }

    public function update(DreMapping $mapping, array $data): DreMapping
    {
        $accountId = (int) ($data['chart_of_account_id'] ?? $mapping->chart_of_account_id);
        $this->guardAnalyticalOnly($accountId);

        $this->guardAgainstClosedPeriod(
            effectiveFrom: (string) ($data['effective_from'] ?? $mapping->effective_from->format('Y-m-d')),
            effectiveTo: array_key_exists('effective_to', $data)
                ? $data['effective_to']
                : $mapping->effective_to?->format('Y-m-d'),
        );

        $this->guardNoTemporalOverlap(
            chartOfAccountId: $accountId,
            costCenterId: array_key_exists('cost_center_id', $data)
                ? $data['cost_center_id']
                : $mapping->cost_center_id,
            effectiveFrom: $data['effective_from'] ?? $mapping->effective_from->format('Y-m-d'),
            effectiveTo: array_key_exists('effective_to', $data)
                ? $data['effective_to']
                : $mapping->effective_to?->format('Y-m-d'),
            excludingId: $mapping->id,
        );

        return DB::transaction(function () use ($mapping, $data) {
            $mapping->fill($this->sanitize($data));
            $mapping->save();

            return $mapping->fresh();
        });
    }

    public function delete(DreMapping $mapping, ?int $deletedByUserId = null, ?string $reason = null): void
    {
        // Deletar um mapping que já teve efeito em período fechado distorce
        // valores históricos. Bloqueia quando o effective_from já caiu no
        // snapshot.
        $this->guardAgainstClosedPeriod(
            effectiveFrom: $mapping->effective_from->format('Y-m-d'),
            effectiveTo: $mapping->effective_to?->format('Y-m-d'),
        );

        DB::transaction(function () use ($mapping, $deletedByUserId, $reason) {
            $mapping->forceFill([
                'deleted_at' => now(),
                'deleted_by_user_id' => $deletedByUserId,
                'deleted_reason' => $reason,
            ])->save();
        });
    }

    /**
     * Atribui várias contas a uma mesma linha gerencial (com ou sem CC)
     * em uma única transação. Útil para classificar dezenas de contas
     * novas que caíram na linha-fantasma após um import.
     *
     * Retorna a quantidade de mapeamentos criados (sucessos). Contas
     * que falham validação (sintética, duplicata temporal) são puladas
     * e registradas nos `skipped` do retorno detalhado via
     * `bulkAssignDetailed()` — este método é o atalho que devolve só
     * a contagem.
     */
    public function bulkAssign(
        array $accountIds,
        ?int $costCenterId,
        int $managementLineId,
        string $effectiveFrom,
        ?int $createdByUserId = null,
    ): int {
        return $this->bulkAssignDetailed(
            $accountIds,
            $costCenterId,
            $managementLineId,
            $effectiveFrom,
            $createdByUserId
        )['created'];
    }

    /**
     * Versão verbose do bulk: retorna contagens detalhadas para UI.
     *
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    public function bulkAssignDetailed(
        array $accountIds,
        ?int $costCenterId,
        int $managementLineId,
        string $effectiveFrom,
        ?int $createdByUserId = null,
    ): array {
        $accountIds = array_values(array_unique(array_map('intval', $accountIds)));

        $accounts = ChartOfAccount::whereIn('id', $accountIds)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use (
            $accountIds,
            $accounts,
            $costCenterId,
            $managementLineId,
            $effectiveFrom,
            $createdByUserId,
            &$created,
            &$skipped,
            &$errors,
        ) {
            foreach ($accountIds as $accountId) {
                $account = $accounts->get($accountId);
                if (! $account) {
                    $skipped++;
                    $errors[] = "Conta id={$accountId} não encontrada.";
                    continue;
                }

                if (! $this->isAnalytical($account)) {
                    $skipped++;
                    $errors[] = sprintf(
                        'Conta %s (%s) é sintética e foi ignorada.',
                        $account->code,
                        $account->name
                    );
                    continue;
                }

                try {
                    $this->guardNoTemporalOverlap(
                        chartOfAccountId: $accountId,
                        costCenterId: $costCenterId,
                        effectiveFrom: $effectiveFrom,
                        effectiveTo: null,
                        excludingId: null,
                    );
                } catch (ValidationException $e) {
                    $skipped++;
                    $errors[] = sprintf(
                        'Conta %s: mapeamento sobreposto — %s',
                        $account->code,
                        collect($e->errors())->flatten()->first()
                    );
                    continue;
                }

                DreMapping::create([
                    'chart_of_account_id' => $accountId,
                    'cost_center_id' => $costCenterId,
                    'dre_management_line_id' => $managementLineId,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                    'created_by_user_id' => $createdByUserId,
                ]);

                $created++;
            }
        });

        return compact('created', 'skipped', 'errors');
    }

    /**
     * Contas analíticas de grupos 3, 4 ou 5 que não têm mapping vigente
     * em `$effectiveOn` (null = hoje). Ignora grupos 1 e 2 (Ativo/Passivo)
     * porque eles não aparecem no DRE.
     *
     * @return Collection<int, ChartOfAccount>
     */
    public function findUnmappedAccounts(?string $effectiveOn = null): Collection
    {
        $effectiveOn = $effectiveOn ?: now()->toDateString();

        return ChartOfAccount::query()
            ->notDeleted()
            ->active()
            ->where(function (Builder $q) {
                $q->where('type', AccountType::ANALYTICAL->value)
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('type')->where('accepts_entries', true);
                    });
            })
            ->whereIn('account_group', [3, 4, 5])
            ->whereDoesntHave('mappings', fn ($q) => $this->effectiveScope($q, $effectiveOn))
            ->orderBy('account_group')
            ->orderBy('code')
            ->with(['defaultManagementClass:id,code,name'])
            ->get();
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    /** Aplica critério temporal de vigência. */
    private function effectiveScope(Builder $q, string $date): Builder
    {
        return $q->whereNull('deleted_at')
            ->where('effective_from', '<=', $date)
            ->where(function (Builder $inner) use ($date) {
                $inner->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    private function isAnalytical(ChartOfAccount $account): bool
    {
        if ($account->type !== null) {
            return $account->type === AccountType::ANALYTICAL;
        }

        return (bool) $account->accepts_entries;
    }

    /**
     * @throws ValidationException
     */
    private function guardAnalyticalOnly(int $chartOfAccountId): void
    {
        $account = ChartOfAccount::find($chartOfAccountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'chart_of_account_id' => 'Conta contábil não encontrada.',
            ]);
        }

        if (! $this->isAnalytical($account)) {
            throw ValidationException::withMessages([
                'chart_of_account_id' => 'Esta conta é sintética e não pode ser mapeada. Mapeie as contas analíticas filhas.',
            ]);
        }
    }

    /**
     * Rejeita mapping que cria sobreposição temporal com outro vigente
     * para o mesmo (account, cc). Intervalos abertos (effective_to=null)
     * são tratados como [from, +∞).
     *
     * @throws ValidationException
     */
    private function guardNoTemporalOverlap(
        int $chartOfAccountId,
        ?int $costCenterId,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $excludingId,
    ): void {
        $newTo = $effectiveTo ?? '9999-12-31';

        $query = DreMapping::query()
            ->whereNull('deleted_at')
            ->where('chart_of_account_id', $chartOfAccountId);

        if ($costCenterId === null) {
            $query->whereNull('cost_center_id');
        } else {
            $query->where('cost_center_id', $costCenterId);
        }

        if ($excludingId !== null) {
            $query->where('id', '!=', $excludingId);
        }

        // Overlap clássico: existing.from <= new.to AND (existing.to IS NULL OR existing.to >= new.from)
        $query->where('effective_from', '<=', $newTo)
            ->where(function (Builder $w) use ($effectiveFrom) {
                $w->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveFrom);
            });

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effective_from' => 'Já existe um mapeamento vigente para esta conta + centro de custo no período informado.',
            ]);
        }
    }

    private function sanitize(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'chart_of_account_id',
            'cost_center_id',
            'dre_management_line_id',
            'effective_from',
            'effective_to',
            'notes',
            'created_by_user_id',
            'updated_by_user_id',
        ]));
    }

    /**
     * Bloqueia operações em mappings cujo intervalo intersecta com algum
     * período fechado ativo. Mudar mapping retroativo falsearia valores
     * históricos que já estão imutáveis via snapshot.
     *
     * Playbook prompt 11: enforcement baseado em `DrePeriodClosing::lastClosedUpTo()`.
     */
    private function guardAgainstClosedPeriod(string $effectiveFrom, ?string $effectiveTo): void
    {
        $lastClosed = \App\Models\DrePeriodClosing::lastClosedUpTo();
        if ($lastClosed === null) {
            return;
        }

        // Mapping entra em conflito se seu intervalo toca o período fechado,
        // ou seja, se effective_from <= lastClosed.
        if ($effectiveFrom <= $lastClosed) {
            throw ValidationException::withMessages([
                'effective_from' => "Data de vigência ({$effectiveFrom}) cai em período já fechado (até {$lastClosed}). Reabra o período antes de alterar mapeamentos.",
            ]);
        }
    }
}
