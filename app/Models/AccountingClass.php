<?php

namespace App\Models;

/**
 * Alias retro-compat para ChartOfAccount.
 *
 * A tabela foi renomeada de `accounting_classes` para `chart_of_accounts`
 * no prompt #1 do DRE. Para não quebrar ~30 arquivos que referenciam
 * `AccountingClass`, mantemos esta classe como herdeira fina. Use
 * `ChartOfAccount` diretamente em código novo.
 *
 * Prompts futuros do DRE (limpeza) irão migrar gradualmente todos os
 * references para `ChartOfAccount`, e esta classe deixa de existir.
 *
 * @deprecated use App\Models\ChartOfAccount
 */
class AccountingClass extends ChartOfAccount
{
    // Intencionalmente vazio. Herda tabela, fillable, casts, relações,
    // scopes e helpers do pai.
}
