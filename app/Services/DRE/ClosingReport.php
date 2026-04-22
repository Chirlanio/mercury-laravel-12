<?php

namespace App\Services\DRE;

/**
 * Relatório de `DrePeriodClosingService::close()`.
 *
 * Contadores separados por escopo ajudam o command/controller a exibir
 * o impacto imediato ao fechar (ex: "125 snapshots Geral + 500 Rede + 2.500 Loja").
 */
class ClosingReport
{
    public int $generalSnapshots = 0;

    public int $networkSnapshots = 0;

    public int $storeSnapshots = 0;

    public int $yearMonths = 0;

    public function total(): int
    {
        return $this->generalSnapshots + $this->networkSnapshots + $this->storeSnapshots;
    }

    public function toArray(): array
    {
        return [
            'general_snapshots' => $this->generalSnapshots,
            'network_snapshots' => $this->networkSnapshots,
            'store_snapshots' => $this->storeSnapshots,
            'year_months' => $this->yearMonths,
            'total' => $this->total(),
        ];
    }
}
