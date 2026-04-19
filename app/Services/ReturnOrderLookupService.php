<?php

namespace App\Services;

use App\Models\Movement;
use Illuminate\Support\Collection;

/**
 * Resolve uma NF/cupom em `movements` (movement_code=2) e devolve o
 * cabeçalho agregado + itens para o modal de criação de devolução.
 *
 * Contexto e-commerce: por padrão filtra por `store_code='Z441'` (loja
 * virtual do CIGAM). O caller pode sobrescrever para testes ou tenants
 * que operem com outro código.
 *
 * Mesma estratégia do ReversalLookupService: agrupa por movement_date
 * para lidar com cupons que se repetem entre anos.
 */
class ReturnOrderLookupService
{
    /**
     * Store code padrão do e-commerce no CIGAM. Configurável via env
     * ECOMMERCE_STORE_CODE ou, no futuro, via config de tenant.
     */
    public const DEFAULT_ECOMMERCE_STORE_CODE = 'Z441';

    /**
     * Busca os movements da NF/cupom e monta o payload de preview.
     *
     * @return array{
     *   found: bool,
     *   invoice_number: string,
     *   store_code: ?string,
     *   movement_date: ?string,
     *   cpf_customer: ?string,
     *   cpf_consultant: ?string,
     *   sale_total: float,
     *   items_count: int,
     *   available_dates: array<int, string>,
     *   items: array<int, array{
     *     movement_id: int,
     *     reference: ?string,
     *     size: ?string,
     *     barcode: ?string,
     *     quantity: float,
     *     unit_price: float,
     *     realized_value: float,
     *   }>,
     * }
     */
    public function lookupInvoice(
        string $invoiceNumber,
        ?string $storeCode = null,
        ?string $movementDate = null
    ): array {
        $invoiceNumber = trim($invoiceNumber);
        $storeCode = $storeCode ?? self::DEFAULT_ECOMMERCE_STORE_CODE;

        /** @var Collection<int, Movement> $allMovements */
        $allMovements = Movement::query()
            ->sales()
            ->where('invoice_number', $invoiceNumber)
            ->where('store_code', $storeCode)
            ->orderByDesc('movement_date')
            ->orderBy('id')
            ->get();

        if ($allMovements->isEmpty()) {
            return [
                'found' => false,
                'invoice_number' => $invoiceNumber,
                'store_code' => $storeCode,
                'movement_date' => null,
                'cpf_customer' => null,
                'cpf_consultant' => null,
                'sale_total' => 0.0,
                'items_count' => 0,
                'available_dates' => [],
                'items' => [],
            ];
        }

        // Ordena datas do mais recente para o mais antigo
        $availableDates = $allMovements
            ->pluck('movement_date')
            ->filter()
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();

        // Data alvo: a informada pelo caller, senão a mais recente
        $targetDate = $movementDate && in_array($movementDate, $availableDates, true)
            ? $movementDate
            : ($availableDates[0] ?? null);

        $movements = $targetDate
            ? $allMovements->filter(
                fn (Movement $m) => $m->movement_date?->format('Y-m-d') === $targetDate
            )
            : $allMovements;

        $header = $movements->first();

        $items = $movements->map(function (Movement $m) {
            // Extrai reference e size do campo ref_size (formato "REF001|38")
            $reference = null;
            $size = null;
            if ($m->ref_size) {
                $parts = explode('|', $m->ref_size, 2);
                $reference = $parts[0] ?? null;
                $size = $parts[1] ?? null;
            }

            return [
                'movement_id' => $m->id,
                'reference' => $reference,
                'size' => $size,
                'barcode' => $m->barcode,
                'quantity' => (float) $m->quantity,
                'unit_price' => (float) $m->sale_price,
                'realized_value' => (float) $m->realized_value,
            ];
        })->values()->all();

        return [
            'found' => true,
            'invoice_number' => $invoiceNumber,
            'store_code' => $header->store_code,
            'movement_date' => $targetDate,
            'cpf_customer' => $header->cpf_customer,
            'cpf_consultant' => $header->cpf_consultant,
            'sale_total' => (float) $movements->sum('realized_value'),
            'items_count' => $movements->count(),
            'available_dates' => $availableDates,
            'items' => $items,
        ];
    }
}
