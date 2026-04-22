<?php

namespace App\Imports\DRE;

use App\Enums\AccountType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Leitor do XLSX do plano de contas (CIGAM).
 *
 * Responsabilidade: ler o arquivo, classificar cada linha e acumular em
 * duas listas — contas (V_Grupo 1..5) e centros de custo (V_Grupo 8) —
 * junto com contadores e erros de leitura. Não persiste nada.
 *
 * A persistência fica em `App\Services\DRE\ChartOfAccountsImporter`,
 * que orquestra upsert + resolução de parent_id (2ª passada) + desativação
 * por sumiço. Separação de preocupações ajuda a testar cada camada em
 * isolamento.
 *
 * Formato completo documentado em `docs/dre-plano-contas-formato.md`.
 */
class ChartOfAccountsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> Contas (V_Grupo 1..5). */
    public array $accounts = [];

    /** @var array<int, array<string, mixed>> Centros de custo (V_Grupo 8). */
    public array $costCenters = [];

    /** @var array<int, string> Erros de validação por linha. */
    public array $errors = [];

    public int $totalRead = 0;

    /** Linha-raiz do plano no ERP (V_Grupo nulo + code nulo + só reduced_code). */
    public int $ignoredMasterRow = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // Maatwebsite com WithHeadingRow: header = linha 1, data
            // começa em $index=0 que corresponde à linha 2 no arquivo.
            $fileRow = $index + 2;
            $this->totalRead++;

            $reducedCode = $this->str($row['codigo_reduzido'] ?? null);
            $group = $this->str($row['v_grupo'] ?? null);
            $type = strtoupper($this->str($row['tipo'] ?? null));
            $code = $this->str($row['classific_conta'] ?? null);
            $name = $this->str($row['nome_conta'] ?? null);

            // Linha-raiz (mestre do plano) — ignorar.
            if ($group === '' && $code === '' && $reducedCode !== '') {
                $this->ignoredMasterRow++;
                continue;
            }

            // Validações mínimas.
            if ($reducedCode === '') {
                $this->errors[] = "Linha {$fileRow}: reduced_code (Codigo Reduzido) ausente.";
                continue;
            }

            if (! in_array($group, ['1', '2', '3', '4', '5', '8'], true)) {
                $this->errors[] = "Linha {$fileRow}: V_Grupo inválido (`{$group}`) — esperado 1..5 ou 8.";
                continue;
            }

            if (! in_array($type, ['S', 'A'], true)) {
                $this->errors[] = "Linha {$fileRow}: Tipo inválido (`{$type}`) — esperado S ou A.";
                continue;
            }

            if ($code === '') {
                $this->errors[] = "Linha {$fileRow}: Classific conta (code) ausente.";
                continue;
            }

            if ($name === '') {
                $this->errors[] = "Linha {$fileRow}: Nome conta ausente.";
                continue;
            }

            $payload = [
                'reduced_code' => $reducedCode,
                'code' => $code,
                'name' => $name,
                'type' => $type === 'S' ? AccountType::SYNTHETIC->value : AccountType::ANALYTICAL->value,
                'is_active' => $this->parseBool($row['vl_ativa'] ?? null, default: true),
                'balance_nature' => $this->parseNature($row['natureza_saldo'] ?? null),
                'is_result_account' => $this->parseBool($row['vl_conta_resultado'] ?? null, default: false),
                'classification_level' => substr_count($code, '.'),
                '_row' => $fileRow,
            ];

            if ($group === '8') {
                $this->costCenters[] = $payload;
            } else {
                $payload['account_group'] = (int) $group;

                // Contas dos grupos 3/4/5 são de resultado mesmo quando
                // o ERP não marca explicitamente (fallback defensivo).
                if (in_array($payload['account_group'], [3, 4, 5], true)) {
                    $payload['is_result_account'] = true;
                }

                $this->accounts[] = $payload;
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    /** Limpa e devolve string trimada. */
    private function str(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /** "True"/"False"/bool/1/0 → boolean. */
    private function parseBool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'TRUE', '1', 'VERDADEIRO', 'SIM', 'S', 'Y', 'YES' => true,
            'FALSE', '0', 'FALSO', 'NÃO', 'NAO', 'N', 'NO' => false,
            default => $default,
        };
    }

    /**
     * Parseia a coluna "Natureza Saldo" — devolve 'D', 'C' ou 'A'
     * quando reconhece o primeiro char, senão null.
     */
    private function parseNature(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $char = strtoupper(substr(trim((string) $value), 0, 1));

        return in_array($char, ['D', 'C', 'A'], true) ? $char : null;
    }
}
