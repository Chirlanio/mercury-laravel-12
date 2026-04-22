<?php

namespace App\Services\DRE;

/**
 * Relatório de `DrePeriodClosingService::reopen()`.
 *
 * `diffs` lista diferenças consolidadas entre o snapshot armazenado e a
 * matriz live atual. Cada entrada é uma tupla:
 *   {scope, scope_id, line_id, line_code, line_name, year_month, snapshot_actual,
 *    current_actual, delta}
 *
 * O controller/notification mostra essas diferenças para o usuário entender
 * o que mudou entre o fechamento e a reabertura. O serviço NÃO altera live;
 * apenas apaga snapshots e marca o DrePeriodClosing como reaberto.
 */
class ReopenReport
{
    /** @var array<int, array<string, mixed>> */
    public array $diffs = [];

    public int $snapshotsDeleted = 0;

    public function hasDiffs(): bool
    {
        return count($this->diffs) > 0;
    }

    public function toArray(): array
    {
        return [
            'diffs' => $this->diffs,
            'diffs_count' => count($this->diffs),
            'snapshots_deleted' => $this->snapshotsDeleted,
        ];
    }
}
