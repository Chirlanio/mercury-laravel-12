<?php

namespace App\Traits;

use App\Support\DreCacheVersion;

/**
 * Invalida o cache da DRE (`DreCacheVersion::invalidate()`) sempre que o
 * modelo é salvo ou deletado.
 *
 * Aplicada em models cuja mudança afeta o resultado da matriz:
 *   - DreMapping, DreManagementLine, DreActual, DreBudget
 *   - DrePeriodClosing, BudgetUpload
 *
 * `ChartOfAccount` NÃO usa este trait porque só `default_management_class_id`
 * importa para a matriz — esse caso tem observer dedicado.
 *
 * Implementação lean: o hook `saved` não dispara quando só `updated_at` mudou
 * (evita thrashing em saves "noop"). `deleted` sempre invalida.
 */
trait InvalidatesDreCacheOnChange
{
    public static function bootInvalidatesDreCacheOnChange(): void
    {
        static::saved(function ($model) {
            if (method_exists($model, 'wasChanged')
                && $model->wasChanged()
                && $model->getChanges() === ['updated_at' => $model->updated_at]) {
                return;
            }

            DreCacheVersion::invalidate();
        });

        static::deleted(function ($model) {
            DreCacheVersion::invalidate();
        });
    }
}
