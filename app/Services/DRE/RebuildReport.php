<?php

namespace App\Services\DRE;

/**
 * Stats de um rebuild de projetor (OrderPaymentToDreProjector ou
 * SaleToDreProjector). Usado pelo command `dre:rebuild-actuals`.
 */
class RebuildReport
{
    public int $truncated = 0;

    public int $projected = 0;

    public int $skipped = 0;

    /** @var array<int,string> Mensagens de skip (até 50 primeiras). */
    public array $skipReasons = [];

    public function addSkip(string $reason): void
    {
        $this->skipped++;
        if (count($this->skipReasons) < 50) {
            $this->skipReasons[] = $reason;
        }
    }

    public function toArray(): array
    {
        return [
            'truncated' => $this->truncated,
            'projected' => $this->projected,
            'skipped' => $this->skipped,
            'skip_reasons' => $this->skipReasons,
        ];
    }
}
