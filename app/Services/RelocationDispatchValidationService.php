<?php

namespace App\Services;

use App\Models\Relocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Valida — on-demand, antes da transição IN_SEPARATION → IN_TRANSIT — que
 * a NF de transferência informada pelo usuário bate com os itens separados
 * do remanejo. Compara `relocation_items.qty_separated` contra a soma de
 * `quantity` em `movements` para a chave (store_code da origem +
 * invoice_number + movement_date), filtrando code=5 (transferência) +
 * entry_exit='S' (saída).
 *
 * Diferenças do RelocationCigamMatcherService: este roda sob demanda no
 * fluxo do usuário (o matcher roda em cron 15min), não muta nada (não
 * salva timestamps nem qty), e retorna estrutura detalhada de divergência
 * pra UI exibir antes de confirmar a transição.
 */
class RelocationDispatchValidationService
{
    public const MOVEMENT_CODE_TRANSFER = 5;

    /**
     * @return array{
     *   nf_found: bool,
     *   total_items_in_invoice: int,
     *   total_items_separated: int,
     *   matched: array<int, array<string, mixed>>,
     *   missing: array<int, array<string, mixed>>,
     *   extra: array<int, array<string, mixed>>,
     *   divergent: array<int, array<string, mixed>>,
     *   has_discrepancies: bool,
     * }
     */
    public function validate(Relocation $relocation, string $invoiceNumber, string $invoiceDate): array
    {
        $relocation->loadMissing(['items', 'originStore']);

        $originCode = $relocation->originStore?->code;

        // Sem origem resolvida não dá pra consultar.
        if (! $originCode) {
            return $this->emptyResult();
        }

        $invoiceByBarcode = $this->fetchInvoiceItems($originCode, $invoiceNumber, $invoiceDate);

        $separatedByBarcode = $this->aggregateSeparated($relocation->items);
        $totalSeparated = array_sum($separatedByBarcode);

        if (empty($invoiceByBarcode)) {
            return [
                'nf_found' => false,
                'total_items_in_invoice' => 0,
                'total_items_separated' => $totalSeparated,
                'matched' => [],
                'missing' => [],
                'extra' => [],
                'divergent' => [],
                'has_discrepancies' => false,
            ];
        }

        $totalInInvoice = array_sum($invoiceByBarcode);

        $matched = [];
        $missing = [];
        $divergent = [];

        foreach ($separatedByBarcode as $barcode => $qtySep) {
            $qtyInv = $invoiceByBarcode[$barcode] ?? 0;
            $entry = array_merge(
                $this->itemMetadata($relocation->items, $barcode),
                [
                    'barcode' => $barcode,
                    'qty_separated' => $qtySep,
                    'qty_in_invoice' => $qtyInv,
                ],
            );

            if ($qtyInv === 0) {
                $missing[] = $entry;
            } elseif ($qtyInv === $qtySep) {
                $matched[] = $entry;
            } else {
                $divergent[] = $entry;
            }
        }

        // Itens na NF que não estão no remanejo (sobrando)
        $extra = [];
        foreach ($invoiceByBarcode as $barcode => $qtyInv) {
            if (! isset($separatedByBarcode[$barcode])) {
                $extra[] = [
                    'barcode' => $barcode,
                    'qty_in_invoice' => $qtyInv,
                ];
            }
        }

        $hasDiscrepancies = ! empty($missing) || ! empty($extra) || ! empty($divergent);

        return [
            'nf_found' => true,
            'total_items_in_invoice' => $totalInInvoice,
            'total_items_separated' => $totalSeparated,
            'matched' => $matched,
            'missing' => $missing,
            'extra' => $extra,
            'divergent' => $divergent,
            'has_discrepancies' => $hasDiscrepancies,
        ];
    }

    /**
     * @return array<string, int> Indexado por barcode → qty agregada na NF
     */
    protected function fetchInvoiceItems(string $storeCode, string $invoiceNumber, string $invoiceDate): array
    {
        $rows = DB::table('movements')
            ->where('movement_code', self::MOVEMENT_CODE_TRANSFER)
            ->where('entry_exit', 'S')
            ->where('store_code', $storeCode)
            ->where('invoice_number', $invoiceNumber)
            ->whereDate('movement_date', $invoiceDate)
            ->select(['barcode', DB::raw('SUM(quantity) as total_qty')])
            ->groupBy('barcode')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if ($r->barcode) {
                $out[$r->barcode] = (int) $r->total_qty;
            }
        }

        return $out;
    }

    /**
     * @param  Collection  $items
     * @return array<string, int>
     */
    protected function aggregateSeparated(Collection $items): array
    {
        $out = [];
        foreach ($items as $it) {
            if (! $it->barcode) continue;
            $out[$it->barcode] = ($out[$it->barcode] ?? 0) + (int) $it->qty_separated;
        }
        return $out;
    }

    /**
     * @param  Collection  $items
     * @return array{product_name: ?string, product_reference: ?string, size: ?string, color: ?string}
     */
    protected function itemMetadata(Collection $items, string $barcode): array
    {
        $item = $items->first(fn ($i) => $i->barcode === $barcode);
        if (! $item) {
            return [
                'product_name' => null,
                'product_reference' => null,
                'size' => null,
                'product_color' => null,
            ];
        }
        return [
            'product_name' => $item->product_name,
            'product_reference' => $item->product_reference,
            'size' => $item->size,
            'product_color' => $item->product_color,
        ];
    }

    protected function emptyResult(): array
    {
        return [
            'nf_found' => false,
            'total_items_in_invoice' => 0,
            'total_items_separated' => 0,
            'matched' => [],
            'missing' => [],
            'extra' => [],
            'divergent' => [],
            'has_discrepancies' => false,
        ];
    }
}
