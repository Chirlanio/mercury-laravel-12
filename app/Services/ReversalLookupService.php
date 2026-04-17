<?php

namespace App\Services;

use App\Models\Movement;
use Illuminate\Support\Collection;

/**
 * Resolve uma NF/cupom em `movements` (movement_code=2) e devolve o
 * cabeçalho agregado + itens para o modal de criação de estorno.
 *
 * Não grava nada. É chamado pelo ReversalController::lookupInvoice via
 * AJAX quando o usuário digita o número da NF no formulário.
 */
class ReversalLookupService
{
    /**
     * Busca os movements da NF/cupom e monta o payload de preview.
     *
     * Como o numero de cupom/NF se repete ao longo dos anos (a sequencia
     * reinicia), pode haver varias vendas com o mesmo invoice_number em
     * datas diferentes. Agrupamos por `movement_date` e:
     *  - Se `$movementDate` for informado, usamos aquela data exata.
     *  - Caso contrario, pegamos a MAIS RECENTE (ordem DESC) por default.
     *
     * O payload inclui `available_dates` para que o frontend mostre um
     * seletor quando houver mais de uma data disponivel.
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
     *     barcode: ?string,
     *     ref_size: ?string,
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

        $query = Movement::query()
            ->sales()
            ->where('invoice_number', $invoiceNumber);

        if ($storeCode !== null) {
            $query->where('store_code', $storeCode);
        }

        /** @var Collection<int, Movement> $allMovements */
        $allMovements = $query->orderByDesc('movement_date')->orderBy('id')->get();

        if ($allMovements->isEmpty()) {
            return [
                'found' => false,
                'invoice_number' => $invoiceNumber,
                'store_code' => null,
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

        // Data alvo: a informada pelo caller, senao a mais recente.
        $targetDate = $movementDate && in_array($movementDate, $availableDates, true)
            ? $movementDate
            : ($availableDates[0] ?? null);

        $movements = $targetDate
            ? $allMovements->filter(
                fn (Movement $m) => $m->movement_date?->format('Y-m-d') === $targetDate
            )
            : $allMovements;

        $header = $movements->first();

        $items = $movements->map(fn (Movement $m) => [
            'movement_id' => $m->id,
            'barcode' => $m->barcode,
            'ref_size' => $m->ref_size,
            'quantity' => (float) $m->quantity,
            'unit_price' => (float) $m->sale_price,
            'realized_value' => (float) $m->realized_value,
        ])->values()->all();

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
