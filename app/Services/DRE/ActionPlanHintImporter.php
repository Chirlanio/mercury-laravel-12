<?php

namespace App\Services\DRE;

use App\Models\ChartOfAccount;
use App\Models\ManagementClass;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Popula `chart_of_accounts.default_management_class_id` a partir do
 * `Action Plan v1.xlsx` (3861 linhas × loja × conta × classe gerencial
 * × mês × valor).
 *
 * Finalidade: dar "dica" (hint) visual na tela de Pendências do DRE —
 * quando o time financeiro for mapear uma conta, já tem uma sugestão
 * da classe gerencial que o próprio Action Plan usou. Não é mapping DRE
 * de verdade (este vai em `dre_mappings`), é só input para UX.
 *
 * Regras:
 *   - Só popula quando `default_management_class_id` é null. Não sobrescreve
 *     valor manual existente.
 *   - Arquivo ausente retorna `file_not_found=true` sem exception (para que
 *     o command do playbook rode mesmo sem o arquivo em ambientes de teste).
 *   - Acumula estatísticas detalhadas em `ActionPlanHintReport`.
 *
 * Formato esperado (definido na análise do arquivo real em
 * `docs/dre-arquitetura.md §decisões / Action Plan`):
 *   Coluna D = Class contábil (ex "3.1.1.01.00012")
 *   Coluna E = Class Gerencial (ex "8.1.09.01")
 *   Header na linha 1.
 */
class ActionPlanHintImporter
{
    public function populateDefaultManagementClass(string $actionPlanPath): ActionPlanHintReport
    {
        $report = new ActionPlanHintReport();

        if (! is_file($actionPlanPath)) {
            $report->fileNotFound = true;

            return $report;
        }

        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($actionPlanPath)->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);

        // Header = linha 1, dados = linha 2+.
        unset($rows[1]);

        // Extrai pares únicos (conta_code, mgmt_class_code).
        $pairs = [];
        foreach ($rows as $row) {
            $report->totalRowsRead++;

            $accountCode = $this->str($row['D'] ?? null);
            $mgmtCode = $this->str($row['E'] ?? null);

            if ($accountCode === '' || $mgmtCode === '') {
                continue;
            }

            $pairs[$accountCode.'|'.$mgmtCode] = [$accountCode, $mgmtCode];
        }

        $report->uniquePairsFound = count($pairs);

        if (empty($pairs)) {
            return $report;
        }

        // Cache de lookups para evitar N queries por par.
        $accountCodes = array_column($pairs, 0);
        $mgmtCodes = array_column($pairs, 1);

        $accountMap = ChartOfAccount::whereIn('code', array_unique($accountCodes))
            ->pluck('id', 'code')
            ->all();

        $mgmtMap = ManagementClass::whereIn('code', array_unique($mgmtCodes))
            ->pluck('id', 'code')
            ->all();

        DB::transaction(function () use ($pairs, $accountMap, $mgmtMap, $report) {
            foreach ($pairs as [$accountCode, $mgmtCode]) {
                $accountId = $accountMap[$accountCode] ?? null;
                $mgmtId = $mgmtMap[$mgmtCode] ?? null;

                if ($accountId === null) {
                    $report->accountsNotFound++;
                    if (count($report->missingAccountCodes) < 20) {
                        $report->missingAccountCodes[] = $accountCode;
                    }
                    continue;
                }

                if ($mgmtId === null) {
                    $report->managementClassesNotFound++;
                    if (count($report->missingManagementClassCodes) < 20) {
                        $report->missingManagementClassCodes[] = $mgmtCode;
                    }
                    continue;
                }

                // Só popula quando o campo está null — não sobrescreve valor manual.
                $affected = ChartOfAccount::where('id', $accountId)
                    ->whereNull('default_management_class_id')
                    ->update(['default_management_class_id' => $mgmtId]);

                if ($affected > 0) {
                    $report->accountsUpdated++;
                } else {
                    $report->accountsSkippedAlreadyHinted++;
                }
            }
        });

        return $report;
    }

    private function str(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
