<?php

namespace App\Services;

use App\Enums\ReversalPartialMode;
use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Models\Bank;
use App\Models\PaymentType;
use App\Models\Reversal;
use App\Models\ReversalReason;
use App\Models\ReversalStatusHistory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa estornos históricos (migração v1 → v2) a partir de planilha
 * XLSX/CSV. Upsert por chave (invoice_number + store_code + amount_original)
 * para ser idempotente em re-imports.
 *
 * Não faz lookup em `movements` — os registros históricos podem não ter
 * as linhas correspondentes no banco v2. Os valores vêm diretamente da
 * planilha.
 *
 * Fluxo em dois passos (igual ao PurchaseOrders import):
 *  1. preview(): lê a planilha, valida linhas, devolve N primeiras
 *     entradas + lista de erros sem persistir nada.
 *  2. import(): re-executa a validação e grava o que passou.
 */
class ReversalImportService
{
    /**
     * Mapeamento de headers PT-BR (v1) → campos canônicos.
     * Aceita variações comuns em maiúscula/minúscula e com acento.
     */
    private const HEADER_MAP = [
        // Identificação
        'nf' => 'invoice_number',
        'cupom' => 'invoice_number',
        'nf_cupom' => 'invoice_number',
        'nf/cupom' => 'invoice_number',
        'doc_nf' => 'invoice_number',
        'documento' => 'invoice_number',
        'numero_nf' => 'invoice_number',
        'numero_documento' => 'invoice_number',
        'invoice_number' => 'invoice_number',

        // Loja
        'loja' => 'store_code',
        'codigo_loja' => 'store_code',
        'store_code' => 'store_code',
        'loja_id' => 'store_code',

        // Datas
        'data' => 'movement_date',
        'data_venda' => 'movement_date',
        'data_da_venda' => 'movement_date',
        'movement_date' => 'movement_date',

        // Cliente
        'cliente' => 'customer_name',
        'nome_cliente' => 'customer_name',
        'customer_name' => 'customer_name',
        'cpf_cliente' => 'cpf_customer',
        'cpf' => 'cpf_customer',
        'cpf_customer' => 'cpf_customer',

        // Consultor / funcionário
        'consultor' => 'cpf_consultant',
        'cpf_consultor' => 'cpf_consultant',
        'cpf_consultant' => 'cpf_consultant',

        // Valores
        'valor_lancado' => 'amount_original',
        'valor_original' => 'amount_original',
        'valor_nf' => 'amount_original',
        'total_nf' => 'sale_total',
        'sale_total' => 'sale_total',
        'valor_correto' => 'amount_correct',
        'valor_estorno' => 'amount_reversal',
        'amount_original' => 'amount_original',
        'amount_correct' => 'amount_correct',
        'amount_reversal' => 'amount_reversal',

        // Tipo / status
        'tipo' => 'type',
        'tipo_estorno' => 'type',
        'type' => 'type',
        'modo' => 'partial_mode',
        'partial_mode' => 'partial_mode',
        'status' => 'status',
        'situacao' => 'status',

        // Motivo
        'motivo' => 'reversal_reason',
        'motivo_estorno' => 'reversal_reason',
        'reason' => 'reversal_reason',

        // Pagamento
        'forma_pagamento' => 'payment_type',
        'forma_pag' => 'payment_type',
        'payment_type' => 'payment_type',
        'bandeira' => 'payment_brand',
        'payment_brand' => 'payment_brand',
        'parcelas' => 'installments_count',
        'qtd_parcelas' => 'installments_count',
        'installments_count' => 'installments_count',
        'nsu' => 'nsu',
        'auto_cartao' => 'authorization_code',
        'autorizacao' => 'authorization_code',
        'authorization_code' => 'authorization_code',

        // PIX
        'tipo_chave_pix' => 'pix_key_type',
        'pix_key_type' => 'pix_key_type',
        'chave_pix' => 'pix_key',
        'pix_key' => 'pix_key',
        'beneficiario' => 'pix_beneficiary',
        'pix_beneficiary' => 'pix_beneficiary',
        'banco' => 'pix_bank',
        'pix_bank' => 'pix_bank',

        // Observações
        'obs' => 'notes',
        'observacao' => 'notes',
        'observacoes' => 'notes',
        'notes' => 'notes',
    ];

    public function __construct(
        protected ReversalService $service,
    ) {}

    /**
     * Lê a planilha e devolve preview + erros de validação. Não persiste.
     *
     * @return array{
     *   rows: array<int, array>,
     *   errors: array<int, array{row:int, messages:array<string>}>,
     *   valid_count: int,
     *   invalid_count: int,
     * }
     */
    public function preview(string $filePath, int $limit = 10): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);

        $rows = [];
        $errors = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($normalized as $idx => $row) {
            $rowNumber = $idx + 2; // +1 header, +1 base-1
            [$data, $rowErrors] = $this->validateRow($row);

            if (empty($rowErrors)) {
                $validCount++;
                if (count($rows) < $limit) {
                    $rows[] = $data;
                }
            } else {
                $invalidCount++;
                if (count($errors) < 50) {
                    $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors];
                }
            }
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
        ];
    }

    /**
     * Persiste os registros válidos. Upsert por
     * (invoice_number, store_code, amount_original) — re-rodar é idempotente.
     *
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   errors: array<int, array{row:int, messages:array<string>}>,
     * }
     */
    public function import(string $filePath, User $actor): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($normalized, $actor, &$created, &$updated, &$skipped, &$errors) {
            foreach ($normalized as $idx => $row) {
                $rowNumber = $idx + 2;
                [$data, $rowErrors] = $this->validateRow($row);

                if (! empty($rowErrors)) {
                    $skipped++;
                    if (count($errors) < 50) {
                        $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors];
                    }
                    continue;
                }

                $existing = Reversal::query()
                    ->where('invoice_number', $data['invoice_number'])
                    ->where('store_code', $data['store_code'])
                    ->where('amount_original', $data['amount_original'])
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    // Upsert: atualiza campos atualizáveis apenas
                    $existing->update(array_filter([
                        'customer_name' => $data['customer_name'],
                        'cpf_customer' => $data['cpf_customer'] ?? null,
                        'cpf_consultant' => $data['cpf_consultant'] ?? null,
                        'sale_total' => $data['sale_total'],
                        'amount_correct' => $data['amount_correct'] ?? null,
                        'amount_reversal' => $data['amount_reversal'],
                        'reversal_reason_id' => $data['reversal_reason_id'],
                        'payment_type_id' => $data['payment_type_id'] ?? null,
                        'payment_brand' => $data['payment_brand'] ?? null,
                        'installments_count' => $data['installments_count'] ?? null,
                        'nsu' => $data['nsu'] ?? null,
                        'authorization_code' => $data['authorization_code'] ?? null,
                        'pix_key_type' => $data['pix_key_type'] ?? null,
                        'pix_key' => $data['pix_key'] ?? null,
                        'pix_beneficiary' => $data['pix_beneficiary'] ?? null,
                        'pix_bank_id' => $data['pix_bank_id'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'updated_by_user_id' => $actor->id,
                    ], fn ($v) => $v !== null && $v !== ''));
                    $updated++;
                    continue;
                }

                $reversal = Reversal::create([
                    'invoice_number' => $data['invoice_number'],
                    'store_code' => $data['store_code'],
                    'movement_date' => $data['movement_date'],
                    'customer_name' => $data['customer_name'],
                    'cpf_customer' => $data['cpf_customer'] ?? null,
                    'cpf_consultant' => $data['cpf_consultant'] ?? null,
                    'sale_total' => $data['sale_total'],
                    'type' => $data['type']->value,
                    'partial_mode' => $data['partial_mode']?->value,
                    'amount_original' => $data['amount_original'],
                    'amount_correct' => $data['amount_correct'] ?? null,
                    'amount_reversal' => $data['amount_reversal'],
                    'status' => $data['status']->value,
                    'reversal_reason_id' => $data['reversal_reason_id'],
                    'payment_type_id' => $data['payment_type_id'] ?? null,
                    'payment_brand' => $data['payment_brand'] ?? null,
                    'installments_count' => $data['installments_count'] ?? null,
                    'nsu' => $data['nsu'] ?? null,
                    'authorization_code' => $data['authorization_code'] ?? null,
                    'pix_key_type' => $data['pix_key_type'] ?? null,
                    'pix_key' => $data['pix_key'] ?? null,
                    'pix_beneficiary' => $data['pix_beneficiary'] ?? null,
                    'pix_bank_id' => $data['pix_bank_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'reversed_at' => $data['status'] === ReversalStatus::REVERSED ? now() : null,
                    'cancelled_at' => $data['status'] === ReversalStatus::CANCELLED ? now() : null,
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ]);

                ReversalStatusHistory::create([
                    'reversal_id' => $reversal->id,
                    'from_status' => null,
                    'to_status' => $data['status']->value,
                    'changed_by_user_id' => $actor->id,
                    'note' => 'Importado da v1 (migração histórica)',
                    'created_at' => now(),
                ]);

                $created++;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Lê arquivo XLSX/CSV como array associativo (heading row → value).
     */
    protected function readFile(string $filePath): array
    {
        $reader = new class implements ToArray, WithHeadingRow {
            public array $rows = [];

            public function array(array $array): void
            {
                $this->rows = $array;
            }
        };

        Excel::import($reader, $filePath);

        return $reader->rows;
    }

    /**
     * Normaliza cabeçalhos: aplica HEADER_MAP e transforma chaves em snake_case.
     */
    protected function normalizeRows(array $raw): array
    {
        return array_map(function ($row) {
            $out = [];
            foreach ($row as $key => $value) {
                $norm = $this->normalizeKey((string) $key);
                $canonical = self::HEADER_MAP[$norm] ?? $norm;
                $out[$canonical] = is_string($value) ? trim($value) : $value;
            }
            return $out;
        }, $raw);
    }

    protected function normalizeKey(string $key): string
    {
        // Remove acentos e converte para snake_case minúsculo
        $key = strtolower(trim($key));
        $key = preg_replace('/[áàâã]/u', 'a', $key);
        $key = preg_replace('/[éèê]/u', 'e', $key);
        $key = preg_replace('/[íì]/u', 'i', $key);
        $key = preg_replace('/[óòôõ]/u', 'o', $key);
        $key = preg_replace('/[úù]/u', 'u', $key);
        $key = str_replace(['ç', ' ', '-'], ['c', '_', '_'], $key);
        $key = preg_replace('/[^a-z0-9_]/', '', $key);
        return (string) $key;
    }

    /**
     * Valida uma linha e devolve [dadosResolvidos, listaDeErros].
     */
    protected function validateRow(array $row): array
    {
        $errors = [];

        $invoice = $this->str($row['invoice_number'] ?? null);
        $storeCode = $this->str($row['store_code'] ?? null);
        $customerName = $this->str($row['customer_name'] ?? null);

        if (! $invoice) $errors[] = 'NF/Cupom obrigatória';
        if (! $storeCode) $errors[] = 'Loja obrigatória';
        if (! $customerName) $errors[] = 'Nome do cliente obrigatório';

        // Valores
        $amountOriginal = $this->decimal($row['amount_original'] ?? null);
        $saleTotal = $this->decimal($row['sale_total'] ?? null) ?? $amountOriginal;
        $amountCorrect = $this->decimal($row['amount_correct'] ?? null);
        $amountReversal = $this->decimal($row['amount_reversal'] ?? null);

        if ($amountOriginal === null) {
            $errors[] = 'Valor original obrigatório';
        }

        // Tipo
        $typeRaw = strtolower($this->str($row['type'] ?? 'total'));
        $type = match (true) {
            in_array($typeRaw, ['total', '1', 'completo'], true) => ReversalType::TOTAL,
            in_array($typeRaw, ['parcial', 'partial', '2'], true) => ReversalType::PARTIAL,
            default => null,
        };
        if (! $type) $errors[] = "Tipo inválido: '{$typeRaw}'";

        // Partial mode (opcional — só para type=partial)
        $partialMode = null;
        if ($type === ReversalType::PARTIAL) {
            $modeRaw = strtolower($this->str($row['partial_mode'] ?? 'by_value'));
            $partialMode = match ($modeRaw) {
                'by_value', 'valor', 'por_valor' => ReversalPartialMode::BY_VALUE,
                'by_item', 'item', 'produto', 'por_produto' => ReversalPartialMode::BY_ITEM,
                default => ReversalPartialMode::BY_VALUE,
            };
        }

        // Calcula amount_reversal se não veio
        if ($amountReversal === null && $amountOriginal !== null) {
            $amountReversal = $type === ReversalType::TOTAL
                ? $amountOriginal
                : max(0, $amountOriginal - ($amountCorrect ?? 0));
        }

        // Status
        $statusRaw = strtolower($this->str($row['status'] ?? 'reversed'));
        $status = match (true) {
            str_contains($statusRaw, 'estornad') || str_contains($statusRaw, 'revers') => ReversalStatus::REVERSED,
            str_contains($statusRaw, 'cancel') => ReversalStatus::CANCELLED,
            str_contains($statusRaw, 'autoriz') || str_contains($statusRaw, 'approv') => ReversalStatus::AUTHORIZED,
            str_contains($statusRaw, 'financeir') => ReversalStatus::PENDING_FINANCE,
            str_contains($statusRaw, 'aprov') => ReversalStatus::PENDING_AUTHORIZATION,
            default => ReversalStatus::REVERSED, // histórico v1 já executado
        };

        // Data — aceita d/m/Y ou Y-m-d
        $movementDate = $this->date($row['movement_date'] ?? null);
        if (! $movementDate) {
            $errors[] = 'Data da venda inválida';
        }

        // Loja: valida existência no catálogo atual
        if ($storeCode) {
            $storeExists = Store::where('code', $storeCode)->exists();
            if (! $storeExists) {
                $errors[] = "Loja '{$storeCode}' não cadastrada";
            }
        }

        // Motivo: resolve por code ou name (case-insensitive)
        $reasonInput = $this->str($row['reversal_reason'] ?? null);
        $reasonId = null;
        if ($reasonInput) {
            $reason = ReversalReason::where('code', strtoupper($reasonInput))
                ->orWhereRaw('LOWER(name) = ?', [strtolower($reasonInput)])
                ->first();
            if ($reason) {
                $reasonId = $reason->id;
            } else {
                $errors[] = "Motivo '{$reasonInput}' não cadastrado";
            }
        } else {
            // Fallback: OUTROS
            $reasonId = ReversalReason::where('code', 'OUTROS')->value('id');
            if (! $reasonId) $errors[] = 'Motivo obrigatório (OUTROS não cadastrado)';
        }

        // Payment type (opcional)
        $paymentTypeId = null;
        $paymentInput = $this->str($row['payment_type'] ?? null);
        if ($paymentInput) {
            $paymentTypeId = PaymentType::whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($paymentInput).'%'])
                ->value('id');
        }

        // Banco PIX (opcional)
        $pixBankId = null;
        $bankInput = $this->str($row['pix_bank'] ?? null);
        if ($bankInput) {
            $pixBankId = Bank::whereRaw('LOWER(bank_name) LIKE ?', ['%'.strtolower($bankInput).'%'])
                ->orWhere('cod_bank', $bankInput)
                ->value('id');
        }

        $data = [
            'invoice_number' => $invoice,
            'store_code' => $storeCode,
            'movement_date' => $movementDate,
            'customer_name' => $customerName,
            'cpf_customer' => $this->str($row['cpf_customer'] ?? null),
            'cpf_consultant' => $this->str($row['cpf_consultant'] ?? null),
            'sale_total' => $saleTotal ?? 0,
            'amount_original' => $amountOriginal ?? 0,
            'amount_correct' => $amountCorrect,
            'amount_reversal' => $amountReversal ?? 0,
            'type' => $type,
            'partial_mode' => $partialMode,
            'status' => $status,
            'reversal_reason_id' => $reasonId,
            'payment_type_id' => $paymentTypeId,
            'payment_brand' => $this->str($row['payment_brand'] ?? null),
            'installments_count' => $this->int($row['installments_count'] ?? null),
            'nsu' => $this->str($row['nsu'] ?? null),
            'authorization_code' => $this->str($row['authorization_code'] ?? null),
            'pix_key_type' => $this->str($row['pix_key_type'] ?? null),
            'pix_key' => $this->str($row['pix_key'] ?? null),
            'pix_beneficiary' => $this->str($row['pix_beneficiary'] ?? null),
            'pix_bank_id' => $pixBankId,
            'notes' => $this->str($row['notes'] ?? null),
        ];

        return [$data, $errors];
    }

    // ------------------------------------------------------------------
    // Casts utilitários
    // ------------------------------------------------------------------

    protected function str($value): ?string
    {
        if ($value === null || $value === '') return null;
        return trim((string) $value);
    }

    protected function decimal($value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float) $value;

        // Aceita "1.234,56" e "1234.56"
        $clean = str_replace(['R$', ' '], '', (string) $value);
        if (substr_count($clean, ',') === 1 && substr_count($clean, '.') >= 0) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    protected function int($value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function date($value): ?string
    {
        if (! $value) return null;

        // Excel pode passar serial numérico (dias desde 1900)
        if (is_numeric($value) && $value > 1000) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);
                return $dt->format('Y-m-d');
            } catch (\Throwable) {
                // fall through para parse string
            }
        }

        $str = (string) $value;

        // d/m/Y
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Y-m-d
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }
}
