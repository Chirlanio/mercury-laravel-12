<?php

namespace App\Events\DRE;

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando uma conta analítica de resultado (grupos 3, 4 ou 5)
 * é criada em `chart_of_accounts`. Listeners podem notificar o time
 * financeiro, alimentar o badge de pendências do sidebar, etc.
 *
 * NÃO é disparado para contas sintéticas nem para contas dos grupos
 * 1/2 (Ativo/Passivo) — elas não entram no DRE.
 */
class AnalyticalAccountCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ChartOfAccount $account)
    {
    }
}
