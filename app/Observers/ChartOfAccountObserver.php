<?php

namespace App\Observers;

use App\Enums\AccountType;
use App\Events\DRE\AnalyticalAccountCreated;
use App\Models\ChartOfAccount;
use App\Support\DreCacheVersion;

/**
 * Observer em `ChartOfAccount` usado pelo módulo DRE.
 *
 * Dispara `AnalyticalAccountCreated` quando uma conta nova entra e
 * qualifica como candidata a mapping DRE:
 *   - type=analytical (folha, recebe lançamento)
 *   - account_group ∈ {3, 4, 5} (Receitas, Custos/Despesas, Resultado)
 *
 * O evento é reservado para listeners futuros (notificação, badge do
 * sidebar, warm-up de cache de pendências). Hoje não há listener — o
 * badge do sidebar usa query direta na tela, não depende do evento.
 *
 * NÃO dispara:
 *   - Para contas sintéticas (elas nunca recebem lançamento).
 *   - Para grupos 1/2 (Ativo/Passivo — fora do DRE).
 *   - Em updates (só em created).
 */
class ChartOfAccountObserver
{
    public function created(ChartOfAccount $account): void
    {
        if (! $this->qualifiesForDre($account)) {
            return;
        }

        AnalyticalAccountCreated::dispatch($account);

        // Conta nova pode aparecer como L99 na matriz → invalida cache.
        DreCacheVersion::invalidate();
    }

    /**
     * Invalida o cache da DRE apenas quando muda a sugestão de mapping
     * (`default_management_class_id`). Renomes/descrições não afetam a
     * matriz, logo não disparam invalidação (lean invalidation).
     */
    public function updated(ChartOfAccount $account): void
    {
        if ($account->wasChanged('default_management_class_id')) {
            DreCacheVersion::invalidate();
        }
    }

    private function qualifiesForDre(ChartOfAccount $account): bool
    {
        $typeIsAnalytical = $account->type instanceof AccountType
            ? $account->type === AccountType::ANALYTICAL
            : ($account->type === AccountType::ANALYTICAL->value || (bool) $account->accepts_entries);

        if (! $typeIsAnalytical) {
            return false;
        }

        $group = $account->account_group instanceof \BackedEnum
            ? $account->account_group->value
            : (int) $account->account_group;

        return in_array($group, [3, 4, 5], true);
    }
}
