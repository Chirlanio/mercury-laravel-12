<?php

namespace App\Services\DRE;

use App\Services\DRE\Contracts\ClosedPeriodReader;

/**
 * Implementação padrão — nunca reporta período fechado. Toda matriz é
 * computada live. Será substituída pelo `DrePeriodSnapshotReader` no
 * prompt 11 (fechamento + snapshot).
 */
class NullClosedPeriodReader implements ClosedPeriodReader
{
    public function closedYearMonths(array $filter): array
    {
        return [];
    }

    public function readSnapshot(array $filter): array
    {
        return [];
    }
}
