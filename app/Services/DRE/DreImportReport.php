<?php

namespace App\Services\DRE;

/**
 * Relatório dos importadores manuais de DRE (actuals/budgets).
 *
 * Estrutura achatada: contadores + lista de erros por linha. Erros vêm em
 * PT-BR prontos para exibição na UI ("Linha 47: conta '1.1.1.01.99999' não
 * encontrada no plano.").
 *
 * Serializável para JSON (HTTP response / status polling) — todos os campos
 * públicos.
 */
class DreImportReport
{
    public int $totalRead = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var array<int, string> Erros PT-BR com número de linha. */
    public array $errors = [];

    public bool $dryRun = false;

    /** Label da versão (só budgets). */
    public ?string $budgetVersion = null;

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
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'budget_version' => $this->budgetVersion,
        ];
    }
}
