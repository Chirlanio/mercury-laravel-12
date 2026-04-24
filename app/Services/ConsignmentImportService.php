<?php

namespace App\Services;

use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa consignações históricas (migração v1 → v2) a partir de planilha
 * XLSX/CSV. Formato flat: UMA LINHA POR ITEM com cabeçalho da consignação
 * denormalizado em cada linha. O service agrupa por
 * (recipient_document_clean + outbound_store_code + outbound_invoice_number)
 * e resolve produto via referência (+ tamanho) usando ConsignmentLookupService
 * (mesma regra M8 do cadastro normal). Itens sem match viram órfãos e
 * aparecem no relatório do preview/import.
 *
 * Fluxo em dois passos (padrão Coupons/Returns/Reversals):
 *  1. preview(): valida, devolve N grupos + órfãos + erros sem persistir.
 *  2. import(): re-valida e grava o que passou. Upsert por chave composta.
 *
 * IMPORTANTE: não dispara notificações nem eventos — só persiste histórico.
 * Transições de status vêm gravadas da v1; se status já vem como
 * 'completed' ou 'cancelled', grava o consignment direto nesse estado.
 */
class ConsignmentImportService
{
    /**
     * Mapeamento de headers PT-BR → campos canônicos.
     */
    private const HEADER_MAP = [
        // Tipo
        'tipo' => 'type',
        'type' => 'type',
        'categoria' => 'type',

        // Destinatário
        'cpf' => 'recipient_document',
        'cnpj' => 'recipient_document',
        'documento' => 'recipient_document',
        'cpf_cnpj' => 'recipient_document',
        'recipient_document' => 'recipient_document',
        'nome' => 'recipient_name',
        'destinatario' => 'recipient_name',
        'cliente' => 'recipient_name',
        'recipient_name' => 'recipient_name',
        'telefone' => 'recipient_phone',
        'celular' => 'recipient_phone',
        'recipient_phone' => 'recipient_phone',
        'email' => 'recipient_email',
        'e_mail' => 'recipient_email',
        'recipient_email' => 'recipient_email',

        // Loja e NF saída
        'loja' => 'outbound_store_code',
        'codigo_loja' => 'outbound_store_code',
        'store_code' => 'outbound_store_code',
        'outbound_store_code' => 'outbound_store_code',
        'nf_saida' => 'outbound_invoice_number',
        'nota_saida' => 'outbound_invoice_number',
        'nf' => 'outbound_invoice_number',
        'invoice_number' => 'outbound_invoice_number',
        'outbound_invoice_number' => 'outbound_invoice_number',
        'data_nf' => 'outbound_invoice_date',
        'data_saida' => 'outbound_invoice_date',
        'data' => 'outbound_invoice_date',
        'outbound_invoice_date' => 'outbound_invoice_date',

        // Consultor
        'consultor' => 'employee_name',
        'consultora' => 'employee_name',
        'colaborador' => 'employee_name',
        'vendedor' => 'employee_name',
        'employee_name' => 'employee_name',
        'matricula' => 'employee_id',
        'employee_id' => 'employee_id',

        // Prazo e status
        'prazo_dias' => 'return_period_days',
        'prazo' => 'return_period_days',
        'return_period_days' => 'return_period_days',
        'data_retorno' => 'expected_return_date',
        'expected_return_date' => 'expected_return_date',
        'status' => 'status',
        'situacao' => 'status',

        // Observações
        'obs' => 'notes',
        'observacao' => 'notes',
        'observacoes' => 'notes',
        'notes' => 'notes',

        // Item
        'referencia' => 'reference',
        'ref' => 'reference',
        'reference' => 'reference',
        'codigo' => 'reference',
        'code_ref' => 'reference',
        'tamanho' => 'size_cigam_code',
        'tam' => 'size_cigam_code',
        'size' => 'size_cigam_code',
        'size_cigam_code' => 'size_cigam_code',
        'quantidade' => 'quantity',
        'qtd' => 'quantity',
        'quantity' => 'quantity',
        'valor_unit' => 'unit_value',
        'valor_unitario' => 'unit_value',
        'preco' => 'unit_value',
        'preco_unitario' => 'unit_value',
        'unit_value' => 'unit_value',
    ];

    private const TYPE_ALIASES = [
        'cliente' => ConsignmentType::CLIENTE,
        'client' => ConsignmentType::CLIENTE,
        'customer' => ConsignmentType::CLIENTE,
        'influencer' => ConsignmentType::INFLUENCER,
        'influenciador' => ConsignmentType::INFLUENCER,
        'ecommerce' => ConsignmentType::ECOMMERCE,
        'e_commerce' => ConsignmentType::ECOMMERCE,
        'loja_virtual' => ConsignmentType::ECOMMERCE,
    ];

    private const STATUS_ALIASES = [
        'draft' => ConsignmentStatus::DRAFT,
        'rascunho' => ConsignmentStatus::DRAFT,
        'pending' => ConsignmentStatus::PENDING,
        'pendente' => ConsignmentStatus::PENDING,
        'partially_returned' => ConsignmentStatus::PARTIALLY_RETURNED,
        'parcial' => ConsignmentStatus::PARTIALLY_RETURNED,
        'parcialmente_retornada' => ConsignmentStatus::PARTIALLY_RETURNED,
        'overdue' => ConsignmentStatus::OVERDUE,
        'atrasada' => ConsignmentStatus::OVERDUE,
        'em_atraso' => ConsignmentStatus::OVERDUE,
        'completed' => ConsignmentStatus::COMPLETED,
        'finalizada' => ConsignmentStatus::COMPLETED,
        'concluida' => ConsignmentStatus::COMPLETED,
        'cancelled' => ConsignmentStatus::CANCELLED,
        'cancelada' => ConsignmentStatus::CANCELLED,
    ];

    public function __construct(
        protected ConsignmentLookupService $lookup,
    ) {
    }

    /**
     * @return array{
     *   groups: array<int, array<string, mixed>>,
     *   errors: array<int, array{row:int, messages:array<string>}>,
     *   valid_groups: int,
     *   invalid_groups: int,
     *   orphans: array<int, array{row:int, reference:string, size:?string}>,
     * }
     */
    public function preview(string $filePath, int $limit = 10): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);
        $grouped = $this->groupRows($normalized);

        $groups = [];
        $errors = [];
        $orphans = [];
        $valid = 0;
        $invalid = 0;

        foreach ($grouped as $groupIdx => $group) {
            [$data, $groupErrors, $groupOrphans] = $this->validateGroup($group);

            if (empty($groupErrors)) {
                $valid++;
                if (count($groups) < $limit) {
                    $groups[] = $data;
                }
            } else {
                $invalid++;
                if (count($errors) < 50) {
                    $errors[] = [
                        'row' => $group[0]['_row_number'],
                        'messages' => $groupErrors,
                    ];
                }
            }

            foreach ($groupOrphans as $orphan) {
                if (count($orphans) < 100) {
                    $orphans[] = $orphan;
                }
            }
        }

        return [
            'groups' => $groups,
            'errors' => $errors,
            'valid_groups' => $valid,
            'invalid_groups' => $invalid,
            'orphans' => $orphans,
        ];
    }

    /**
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   items_created: int,
     *   orphan_items: int,
     *   errors: array<int, array{row:int, messages:array<string>}>,
     * }
     */
    public function import(string $filePath, User $actor): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);
        $grouped = $this->groupRows($normalized);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $itemsCreated = 0;
        $orphanItems = 0;
        $errors = [];

        DB::transaction(function () use (
            $grouped, $actor, &$created, &$updated, &$skipped,
            &$itemsCreated, &$orphanItems, &$errors
        ) {
            foreach ($grouped as $group) {
                [$data, $groupErrors, $orphans] = $this->validateGroup($group);

                $orphanItems += count($orphans);

                if (! empty($groupErrors)) {
                    $skipped++;
                    if (count($errors) < 50) {
                        $errors[] = [
                            'row' => $group[0]['_row_number'],
                            'messages' => $groupErrors,
                        ];
                    }
                    continue;
                }

                // Upsert por (recipient_document_clean + outbound_store_code + outbound_invoice_number)
                $existing = Consignment::query()
                    ->where('recipient_document_clean', $data['recipient_document_clean'])
                    ->where('outbound_store_code', $data['outbound_store_code'])
                    ->where('outbound_invoice_number', $data['outbound_invoice_number'])
                    ->whereNull('deleted_at')
                    ->first();

                $consignmentPayload = [
                    'type' => $data['type']->value,
                    'store_id' => $data['store_id'],
                    'employee_id' => $data['employee_id'] ?? null,
                    'recipient_name' => $data['recipient_name'],
                    'recipient_document' => $data['recipient_document'],
                    'recipient_document_clean' => $data['recipient_document_clean'],
                    'recipient_phone' => $data['recipient_phone'] ?? null,
                    'recipient_email' => $data['recipient_email'] ?? null,
                    'outbound_invoice_number' => $data['outbound_invoice_number'],
                    'outbound_invoice_date' => $data['outbound_invoice_date'],
                    'outbound_store_code' => $data['outbound_store_code'],
                    'return_period_days' => $data['return_period_days'],
                    'expected_return_date' => $data['expected_return_date'],
                    'status' => $data['status']->value,
                    'notes' => $data['notes'] ?? null,
                    'updated_by_user_id' => $actor->id,
                ];

                if ($existing) {
                    $existing->update($consignmentPayload);
                    $consignment = $existing;
                    $updated++;
                    // Re-import sobrescreve itens antigos
                    $consignment->items()->delete();
                } else {
                    $consignmentPayload['created_by_user_id'] = $actor->id;
                    // Issued_at e completed_at conforme status de chegada
                    if ($data['status'] !== ConsignmentStatus::DRAFT) {
                        $consignmentPayload['issued_at'] = $data['outbound_invoice_date'];
                    }
                    if ($data['status'] === ConsignmentStatus::COMPLETED) {
                        $consignmentPayload['completed_at'] = now();
                        $consignmentPayload['completed_by_user_id'] = $actor->id;
                    }
                    if ($data['status'] === ConsignmentStatus::CANCELLED) {
                        $consignmentPayload['cancelled_at'] = now();
                        $consignmentPayload['cancelled_reason'] = $data['notes'] ?: 'Importado da v1 como cancelado';
                    }
                    $consignment = Consignment::create($consignmentPayload);
                    $created++;
                }

                foreach ($data['items'] as $item) {
                    ConsignmentItem::create([
                        'consignment_id' => $consignment->id,
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'reference' => $item['reference'],
                        'barcode' => $item['barcode'] ?? null,
                        'size_label' => $item['size_label'] ?? null,
                        'size_cigam_code' => $item['size_cigam_code'] ?? null,
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_value' => $item['unit_value'],
                        'total_value' => round($item['quantity'] * $item['unit_value'], 2),
                        'status' => $this->deriveItemStatus($data['status']),
                    ]);
                    $itemsCreated++;
                }

                // Recalcula totais agregados
                app(ConsignmentService::class)->refreshTotals($consignment);

                // Histórico sintético se status != draft
                if ($data['status'] !== ConsignmentStatus::DRAFT) {
                    $consignment->statusHistory()->create([
                        'consignment_id' => $consignment->id,
                        'from_status' => null,
                        'to_status' => $data['status']->value,
                        'changed_by_user_id' => $actor->id,
                        'note' => 'Importado via planilha v1',
                        'context' => ['imported' => true],
                        'created_at' => now(),
                    ]);
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'items_created' => $itemsCreated,
            'orphan_items' => $orphanItems,
            'errors' => $errors,
        ];
    }

    /**
     * Item status coerente com o status do header — evita estado derivado
     * inconsistente (ex: consignação completed com items pendentes).
     */
    private function deriveItemStatus(ConsignmentStatus $status): string
    {
        return match ($status) {
            ConsignmentStatus::COMPLETED => 'returned',
            ConsignmentStatus::CANCELLED => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * @return array{0: array<string,mixed>, 1: array<int,string>, 2: array<int, array>}
     */
    private function validateGroup(array $group): array
    {
        $errors = [];
        $orphans = [];
        $first = $group[0];

        // Tipo
        $typeRaw = strtolower(trim((string) ($first['type'] ?? '')));
        $type = self::TYPE_ALIASES[$typeRaw] ?? null;
        if (! $type) {
            $errors[] = 'Tipo inválido ou faltando (Cliente/Influencer/E-commerce).';
            return [[], $errors, $orphans];
        }

        // Loja
        $storeCode = strtoupper(trim((string) ($first['outbound_store_code'] ?? '')));
        $store = null;
        if ($storeCode === '') {
            $errors[] = 'Loja (código) é obrigatória.';
        } else {
            $store = Store::where('code', $storeCode)->first();
            if (! $store) {
                $errors[] = "Loja '{$storeCode}' não encontrada.";
            }
        }

        // NF saída + data
        $invoiceNumber = trim((string) ($first['outbound_invoice_number'] ?? ''));
        if ($invoiceNumber === '') {
            $errors[] = 'Número da NF de saída é obrigatório.';
        }

        $invoiceDate = null;
        try {
            $invoiceDate = $this->parseDate($first['outbound_invoice_date'] ?? null);
            if (! $invoiceDate) {
                $errors[] = 'Data da NF de saída é obrigatória.';
            }
        } catch (\Throwable $e) {
            $errors[] = "Data da NF inválida ({$first['outbound_invoice_date']}).";
        }

        // Destinatário
        $name = trim((string) ($first['recipient_name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Nome do destinatário é obrigatório.';
        }

        $docRaw = (string) ($first['recipient_document'] ?? '');
        $docClean = preg_replace('/\D/', '', $docRaw);
        if ($docClean === '' || ! in_array(strlen($docClean), [11, 14], true)) {
            $errors[] = 'Documento inválido (precisa ter 11 CPF ou 14 CNPJ dígitos).';
        }

        // Employee (só pra cliente)
        $employeeId = null;
        if ($type->requiresEmployee()) {
            $empIdRaw = $first['employee_id'] ?? null;
            $empName = trim((string) ($first['employee_name'] ?? ''));
            if ($empIdRaw) {
                $emp = Employee::find((int) $empIdRaw);
                if (! $emp) {
                    $errors[] = "Consultor ID {$empIdRaw} não encontrado.";
                } else {
                    $employeeId = $emp->id;
                }
            } elseif ($empName && $storeCode) {
                $emp = Employee::where('store_id', $storeCode)
                    ->whereRaw('LOWER(name) = ?', [strtolower($empName)])
                    ->first();
                if (! $emp) {
                    $errors[] = "Consultor '{$empName}' não encontrado na loja {$storeCode}.";
                } else {
                    $employeeId = $emp->id;
                }
            } else {
                $errors[] = 'Consultor é obrigatório para consignação tipo Cliente.';
            }
        }

        // Status (opcional — default pending)
        $status = ConsignmentStatus::PENDING;
        if (! empty($first['status'])) {
            $statusRaw = strtolower(trim((string) $first['status']));
            $mapped = self::STATUS_ALIASES[$statusRaw] ?? null;
            if (! $mapped) {
                $errors[] = "Status '{$first['status']}' inválido.";
            } else {
                $status = $mapped;
            }
        }

        // Prazo + data esperada de retorno
        $returnPeriod = (int) ($first['return_period_days'] ?? $type->defaultReturnPeriodDays());
        $expectedReturn = null;
        try {
            $expectedReturn = $this->parseDate($first['expected_return_date'] ?? null);
        } catch (\Throwable $e) {
            // ignora — vamos derivar
        }
        if (! $expectedReturn && $invoiceDate) {
            $expectedReturn = (new \DateTime($invoiceDate))
                ->modify("+{$returnPeriod} days")
                ->format('Y-m-d');
        }

        // Items — cada linha do grupo vira um item
        $items = [];
        foreach ($group as $row) {
            $reference = trim((string) ($row['reference'] ?? ''));
            $sizeCigam = $row['size_cigam_code'] !== null ? trim((string) $row['size_cigam_code']) : null;
            $quantity = (int) ($row['quantity'] ?? 0);
            $unitValue = (float) str_replace(',', '.', (string) ($row['unit_value'] ?? 0));

            if ($reference === '' || $quantity <= 0 || $unitValue <= 0) {
                $errors[] = "Linha {$row['_row_number']}: referência, quantidade (>0) e valor unitário (>0) são obrigatórios.";
                continue;
            }

            $resolved = $this->lookup->resolveProductVariant(
                reference: $reference,
                barcode: null,
                sizeCigamCode: $sizeCigam,
            );

            if (! $resolved || ! $resolved['product']) {
                // Órfão — produto não encontrado no catálogo local
                $orphans[] = [
                    'row' => $row['_row_number'],
                    'reference' => $reference,
                    'size' => $sizeCigam,
                ];
                continue;
            }

            $items[] = [
                'product_id' => $resolved['product']->id,
                'product_variant_id' => $resolved['variant']?->id,
                'reference' => $resolved['product']->reference ?: $reference,
                'barcode' => $resolved['variant']?->barcode,
                'size_label' => $resolved['variant']?->size?->name ?? $sizeCigam,
                'size_cigam_code' => $resolved['variant']?->size_cigam_code ?? $sizeCigam,
                'description' => $resolved['product']->description,
                'quantity' => $quantity,
                'unit_value' => round($unitValue, 2),
            ];
        }

        if (empty($items) && empty($errors)) {
            $errors[] = 'Nenhum item válido — todos os produtos caíram em órfãos.';
        }

        if (! empty($errors)) {
            return [[], $errors, $orphans];
        }

        return [[
            'type' => $type,
            'store_id' => $store->id,
            'employee_id' => $employeeId,
            'recipient_name' => strtoupper($name),
            'recipient_document' => $docRaw ?: null,
            'recipient_document_clean' => $docClean,
            'recipient_phone' => trim((string) ($first['recipient_phone'] ?? '')) ?: null,
            'recipient_email' => trim((string) ($first['recipient_email'] ?? '')) ?: null,
            'outbound_invoice_number' => $invoiceNumber,
            'outbound_invoice_date' => $invoiceDate,
            'outbound_store_code' => $storeCode,
            'return_period_days' => $returnPeriod,
            'expected_return_date' => $expectedReturn,
            'status' => $status,
            'notes' => trim((string) ($first['notes'] ?? '')) ?: null,
            'items' => $items,
        ], $errors, $orphans];
    }

    private function readFile(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            throw new \InvalidArgumentException('Arquivo deve ser XLSX, XLS ou CSV.');
        }

        $import = new class implements ToArray, WithHeadingRow {
            public array $rows = [];

            public function array(array $array): void
            {
                $this->rows = $array;
            }
        };

        Excel::import($import, $filePath);

        return $import->rows;
    }

    /**
     * Normaliza headers via HEADER_MAP + injeta número de linha original
     * (para mensagens de erro).
     */
    private function normalizeRows(array $raw): array
    {
        $normalized = [];
        foreach ($raw as $idx => $row) {
            $mapped = ['_row_number' => $idx + 2]; // +1 header, +1 base-1
            foreach ($row as $key => $value) {
                $canonicalKey = self::HEADER_MAP[$this->slugify((string) $key)] ?? null;
                if ($canonicalKey) {
                    $mapped[$canonicalKey] = is_string($value) ? trim($value) : $value;
                }
            }
            if (count($mapped) > 1) {
                $normalized[] = $mapped;
            }
        }

        return $normalized;
    }

    /**
     * Agrupa linhas por chave composta (documento + loja + NF saída).
     * Linhas sem chave completa viram um grupo-por-linha (ficam com erro).
     *
     * @return array<int, array<int, array>>
     */
    private function groupRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $doc = preg_replace('/\D/', '', (string) ($row['recipient_document'] ?? ''));
            $store = strtoupper(trim((string) ($row['outbound_store_code'] ?? '')));
            $invoice = trim((string) ($row['outbound_invoice_number'] ?? ''));

            $key = $doc && $store && $invoice
                ? "{$doc}|{$store}|{$invoice}"
                : 'row_'.$row['_row_number'];

            $groups[$key][] = $row;
        }

        return array_values($groups);
    }

    private function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[áàãâä]/u', 'a', $s);
        $s = preg_replace('/[éèêë]/u', 'e', $s);
        $s = preg_replace('/[íìîï]/u', 'i', $s);
        $s = preg_replace('/[óòõôö]/u', 'o', $s);
        $s = preg_replace('/[úùûü]/u', 'u', $s);
        $s = preg_replace('/[ç]/u', 'c', $s);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);

        return trim($s, '_');
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $ts = ((int) $value - 25569) * 86400;

            return date('Y-m-d', $ts);
        }

        $str = trim((string) $value);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $str, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return (new \DateTime($str))->format('Y-m-d');
    }
}
