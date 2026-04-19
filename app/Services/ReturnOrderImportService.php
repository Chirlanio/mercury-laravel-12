<?php

namespace App\Services;

use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Models\ReturnOrder;
use App\Models\ReturnOrderStatusHistory;
use App\Models\ReturnReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa devoluções históricas (migração v1 → v2) a partir de planilha
 * XLSX/CSV. Upsert por chave (invoice_number + store_code + type) para
 * ser idempotente em re-imports.
 *
 * Não faz lookup em `movements` — os registros históricos podem não ter
 * as linhas correspondentes sincronizadas no v2. Os valores vêm
 * diretamente da planilha. Store code default = Z441 (e-commerce).
 *
 * Fluxo em dois passos (padrão PurchaseOrders/Reversals):
 *  1. preview(): valida linhas, devolve N primeiras + erros sem persistir.
 *  2. import(): re-executa validação e grava o que passou.
 */
class ReturnOrderImportService
{
    public const DEFAULT_STORE_CODE = 'Z441';

    /**
     * Mapeamento de headers PT-BR → campos canônicos.
     */
    private const HEADER_MAP = [
        // Identificação
        'nf' => 'invoice_number',
        'cupom' => 'invoice_number',
        'protocol' => 'invoice_number',
        'protocolo' => 'invoice_number',
        'numero_nf' => 'invoice_number',
        'numero_pedido' => 'invoice_number',
        'invoice_number' => 'invoice_number',

        'loja' => 'store_code',
        'codigo_loja' => 'store_code',
        'store_code' => 'store_code',

        'data' => 'movement_date',
        'data_venda' => 'movement_date',
        'data_da_venda' => 'movement_date',
        'movement_date' => 'movement_date',

        // Cliente
        'cliente' => 'customer_name',
        'nome_cliente' => 'customer_name',
        'client_name' => 'customer_name',
        'customer_name' => 'customer_name',
        'cpf' => 'cpf_customer',
        'cpf_cliente' => 'cpf_customer',
        'cpf_customer' => 'cpf_customer',

        // Tipo / categoria / status
        'tipo' => 'type',
        'type' => 'type',
        'categoria' => 'reason_category',
        'categoria_motivo' => 'reason_category',
        'reason_category' => 'reason_category',
        'motivo' => 'return_reason',
        'motivo_devolucao' => 'return_reason',
        'reason' => 'return_reason',
        'status' => 'status',
        'situacao' => 'status',

        // Valores
        'valor_itens' => 'amount_items',
        'total_itens' => 'amount_items',
        'amount_items' => 'amount_items',
        'valor_reembolso' => 'refund_amount',
        'reembolso' => 'refund_amount',
        'refund_amount' => 'refund_amount',
        'total_nf' => 'sale_total',
        'sale_total' => 'sale_total',

        // Logística
        'rastreio' => 'reverse_tracking_code',
        'codigo_rastreio' => 'reverse_tracking_code',
        'reverse_tracking_code' => 'reverse_tracking_code',

        // Observações
        'obs' => 'notes',
        'observacao' => 'notes',
        'observacoes' => 'notes',
        'notes' => 'notes',
    ];

    /**
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

                $existing = ReturnOrder::query()
                    ->where('invoice_number', $data['invoice_number'])
                    ->where('store_code', $data['store_code'])
                    ->where('type', $data['type']->value)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    $existing->update(array_filter([
                        'customer_name' => $data['customer_name'],
                        'cpf_customer' => $data['cpf_customer'] ?? null,
                        'sale_total' => $data['sale_total'],
                        'amount_items' => $data['amount_items'],
                        'refund_amount' => $data['refund_amount'] ?? null,
                        'reason_category' => $data['reason_category']->value,
                        'return_reason_id' => $data['return_reason_id'] ?? null,
                        'reverse_tracking_code' => $data['reverse_tracking_code'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'updated_by_user_id' => $actor->id,
                    ], fn ($v) => $v !== null && $v !== ''));
                    $updated++;
                    continue;
                }

                $order = ReturnOrder::create([
                    'invoice_number' => $data['invoice_number'],
                    'store_code' => $data['store_code'],
                    'movement_date' => $data['movement_date'],
                    'customer_name' => $data['customer_name'],
                    'cpf_customer' => $data['cpf_customer'] ?? null,
                    'sale_total' => $data['sale_total'],
                    'type' => $data['type']->value,
                    'amount_items' => $data['amount_items'],
                    'refund_amount' => $data['refund_amount'] ?? null,
                    'status' => $data['status']->value,
                    'reason_category' => $data['reason_category']->value,
                    'return_reason_id' => $data['return_reason_id'] ?? null,
                    'reverse_tracking_code' => $data['reverse_tracking_code'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'approved_at' => in_array($data['status'], [
                        ReturnStatus::APPROVED,
                        ReturnStatus::AWAITING_PRODUCT,
                        ReturnStatus::PROCESSING,
                        ReturnStatus::COMPLETED,
                    ], true) ? now() : null,
                    'completed_at' => $data['status'] === ReturnStatus::COMPLETED ? now() : null,
                    'cancelled_at' => $data['status'] === ReturnStatus::CANCELLED ? now() : null,
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ]);

                ReturnOrderStatusHistory::create([
                    'return_order_id' => $order->id,
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

    protected function validateRow(array $row): array
    {
        $errors = [];

        $invoice = $this->str($row['invoice_number'] ?? null);
        $storeCode = $this->str($row['store_code'] ?? null) ?? self::DEFAULT_STORE_CODE;
        $customerName = $this->str($row['customer_name'] ?? null);

        if (! $invoice) $errors[] = 'NF/Cupom obrigatória';
        if (! $customerName) $errors[] = 'Nome do cliente obrigatório';

        // Tipo
        $typeRaw = strtolower($this->str($row['type'] ?? 'troca'));
        $type = match (true) {
            in_array($typeRaw, ['troca', 'trocas'], true) => ReturnType::TROCA,
            in_array($typeRaw, ['estorno', 'estornos'], true) => ReturnType::ESTORNO,
            in_array($typeRaw, ['credito', 'crédito'], true) => ReturnType::CREDITO,
            default => null,
        };
        if (! $type) $errors[] = "Tipo inválido: '{$typeRaw}' (use troca, estorno ou credito)";

        // Categoria do motivo
        $categoryRaw = strtolower($this->str($row['reason_category'] ?? 'outro'));
        $category = match (true) {
            str_contains($categoryRaw, 'arrepend') => ReturnReasonCategory::ARREPENDIMENTO,
            str_contains($categoryRaw, 'defeito') => ReturnReasonCategory::DEFEITO,
            str_contains($categoryRaw, 'diverg') => ReturnReasonCategory::DIVERGENCIA,
            str_contains($categoryRaw, 'tamanho') || str_contains($categoryRaw, 'cor') => ReturnReasonCategory::TAMANHO_COR,
            str_contains($categoryRaw, 'nao_receb') || str_contains($categoryRaw, 'extravio') => ReturnReasonCategory::NAO_RECEBIDO,
            default => ReturnReasonCategory::OUTRO,
        };

        // Status — aceita valores PT-BR da v1
        $statusRaw = strtolower($this->str($row['status'] ?? 'completo'));
        $status = match (true) {
            str_contains($statusRaw, 'pendent') => ReturnStatus::PENDING,
            str_contains($statusRaw, 'aprovad') => ReturnStatus::APPROVED,
            str_contains($statusRaw, 'aguardando_prod') || str_contains($statusRaw, 'aguardando produ') => ReturnStatus::AWAITING_PRODUCT,
            str_contains($statusRaw, 'processand') || str_contains($statusRaw, 'processing') => ReturnStatus::PROCESSING,
            str_contains($statusRaw, 'completo') || str_contains($statusRaw, 'concluido') || str_contains($statusRaw, 'completed') => ReturnStatus::COMPLETED,
            str_contains($statusRaw, 'cancel') => ReturnStatus::CANCELLED,
            default => ReturnStatus::COMPLETED, // v1 históricos tendem a estar concluídos
        };

        // Valores
        $amountItems = $this->decimal($row['amount_items'] ?? null) ?? 0;
        $refundAmount = $this->decimal($row['refund_amount'] ?? null);
        $saleTotal = $this->decimal($row['sale_total'] ?? null) ?? $amountItems;

        if ($amountItems <= 0) {
            $errors[] = 'Valor dos itens obrigatório (maior que zero)';
        }

        // Estorno/crédito precisa de refund_amount
        if ($type && $type->requiresRefundAmount() && ($refundAmount === null || $refundAmount <= 0)) {
            // Se não veio, fallback para amount_items
            $refundAmount = $amountItems;
        }

        // Data
        $movementDate = $this->date($row['movement_date'] ?? null) ?? now()->toDateString();

        // Motivo específico (FK opcional)
        $reasonId = null;
        $reasonInput = $this->str($row['return_reason'] ?? null);
        if ($reasonInput) {
            $reasonId = ReturnReason::whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($reasonInput).'%'])
                ->orWhere('code', strtoupper($reasonInput))
                ->value('id');
        }

        $data = [
            'invoice_number' => $invoice,
            'store_code' => $storeCode,
            'movement_date' => $movementDate,
            'customer_name' => $customerName,
            'cpf_customer' => $this->str($row['cpf_customer'] ?? null),
            'sale_total' => $saleTotal,
            'amount_items' => $amountItems,
            'refund_amount' => $refundAmount,
            'type' => $type,
            'reason_category' => $category,
            'return_reason_id' => $reasonId,
            'status' => $status,
            'reverse_tracking_code' => $this->str($row['reverse_tracking_code'] ?? null),
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

        $clean = str_replace(['R$', ' '], '', (string) $value);
        if (substr_count($clean, ',') === 1) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    protected function date($value): ?string
    {
        if (! $value) return null;

        if (is_numeric($value) && $value > 1000) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);
                return $dt->format('Y-m-d');
            } catch (\Throwable) {
                // continue
            }
        }

        $str = (string) $value;

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }
}
