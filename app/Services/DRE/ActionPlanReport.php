<?php

namespace App\Services\DRE;

/**
 * Relatório do `ActionPlanImporter::import()`.
 *
 * Separa contadores de leitura (o XLSX) dos de persistência (o DB), mantém
 * erros em PT-BR prontos para display, e flag de dry-run.
 */
class ActionPlanReport
{
    public int $totalRead = 0;

    public int $inserted = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public bool $dryRun = false;

    public string $budgetVersion = '';

    public function addError(int $row, string $message): void
    {
        $this->errors[] = "Linha {$row}: {$message}";
        $this->skipped++;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'total_read' => $this->totalRead,
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'budget_version' => $this->budgetVersion,
        ];
    }
}
