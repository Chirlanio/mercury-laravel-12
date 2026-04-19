<?php

namespace App\Services;

use App\Enums\BudgetUploadType;
use App\Models\BudgetUpload;

/**
 * Regras de versionamento de orçamento (paridade v1 adms_budgets_uploads).
 *
 * Versionamento sempre escopado por (year + scope_label). Um scope/year
 * tem uma versão ativa por vez; novos uploads geram versões maiores e
 * desativam a anterior (feito no BudgetService, não aqui).
 *
 * Regras:
 *   1. Não há upload anterior do mesmo (year, scope) → retorna 1.0
 *   2. Último upload era de ANO DIFERENTE → reset para 1.0
 *      (ex: último foi 2025 v2.3, novo é 2026 → 1.0 não 2.4)
 *   3. Mesmo ano + type=NOVO → major += 1, minor = 0 (1.0 → 2.0)
 *   4. Mesmo ano + type=AJUSTE → minor += 1 (1.0 → 1.01, 1.01 → 1.02)
 *
 * Casos de edge:
 *   - Se existir uma versão deletada do mesmo (year, scope), é ignorada —
 *     next version parte da última NÃO-deletada.
 */
class BudgetVersionService
{
    /**
     * Calcula o próximo par (major, minor, version_label) para um upload.
     *
     * @return array{major: int, minor: int, label: string}
     */
    public function resolveNextVersion(
        int $year,
        string $scopeLabel,
        BudgetUploadType $type
    ): array {
        // Busca o último upload NÃO-deletado para este scope+year
        $lastSameYear = BudgetUpload::query()
            ->where('scope_label', $scopeLabel)
            ->where('year', $year)
            ->whereNull('deleted_at')
            ->orderByDesc('major_version')
            ->orderByDesc('minor_version')
            ->first();

        if ($lastSameYear) {
            // Regra 3 ou 4: mesmo ano
            return $this->incrementFrom($lastSameYear, $type);
        }

        // Não há upload deste year+scope. Pode ser primeiro absoluto ou
        // primeiro do ano (regra 2). Em qualquer caso → 1.0
        return [
            'major' => 1,
            'minor' => 0,
            'label' => BudgetUpload::composeVersionLabel(1, 0),
        ];
    }

    /**
     * Incrementa a partir de um upload existente conforme o type.
     *
     * @return array{major: int, minor: int, label: string}
     */
    protected function incrementFrom(BudgetUpload $last, BudgetUploadType $type): array
    {
        if ($type === BudgetUploadType::NOVO) {
            $major = $last->major_version + 1;
            $minor = 0;
        } else {
            $major = $last->major_version;
            $minor = $last->minor_version + 1;
        }

        return [
            'major' => $major,
            'minor' => $minor,
            'label' => BudgetUpload::composeVersionLabel($major, $minor),
        ];
    }
}
