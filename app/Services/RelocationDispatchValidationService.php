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
    /**
     * @param  array<int, array{id: int, qty_separated: int}>|null  $separatedItemsOverride
     *   Quando o usuário ajusta qty_separated na UI mas ainda não salvou,
     *   o frontend envia esse snapshot pra que a validação use os valores
     *   do form em vez dos persistidos no banco.
     */
    public function validate(
        Relocation $relocation,
        string $invoiceNumber,
        string $invoiceDate,
        ?array $separatedItemsOverride = null,
    ): array {
        $relocation->loadMissing(['items', 'originStore']);

        $originCode = $relocation->originStore?->code;

        // Sem origem resolvida não dá pra consultar.
        if (! $originCode) {
            return $this->emptyResult();
        }

        $invoiceByBarcode = $this->fetchInvoiceItems($originCode, $invoiceNumber, $invoiceDate);

        $separatedByBarcode = $this->aggregateSeparated($relocation->items, $separatedItemsOverride);
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

        // Itens na NF que não estão no remanejo (sobrando). Faz lookup
        // no catálogo pra identificar product_name/reference/cor/tamanho —
        // só ter o barcode dificulta o usuário entender o que sobrou.
        $extraBarcodes = array_diff(array_keys($invoiceByBarcode), array_keys($separatedByBarcode));
        $catalogByBarcode = $this->lookupCatalog($extraBarcodes);

        $extra = [];
        foreach ($extraBarcodes as $barcode) {
            $meta = $catalogByBarcode[$barcode] ?? [
                'product_name' => null,
                'product_reference' => null,
                'size' => null,
                'product_color' => null,
            ];
            $extra[] = array_merge($meta, [
                'barcode' => $barcode,
                'qty_in_invoice' => $invoiceByBarcode[$barcode],
            ]);
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
     * Agrega qty_separated por barcode. Quando $override está presente
     * (mapa item_id → qty_separated vindo do frontend), usa os valores
     * do form em vez dos persistidos no banco — assim a validação
     * reflete o snapshot atual da UI sem exigir save prévio.
     *
     * @param  Collection  $items
     * @param  array<int, array{id: int, qty_separated: int}>|null  $override
     * @return array<string, int>
     */
    protected function aggregateSeparated(Collection $items, ?array $override = null): array
    {
        $overrideById = [];
        if (is_array($override)) {
            foreach ($override as $entry) {
                if (! isset($entry['id'])) continue;
                $overrideById[(int) $entry['id']] = (int) ($entry['qty_separated'] ?? 0);
            }
        }

        $out = [];
        foreach ($items as $it) {
            if (! $it->barcode) continue;
            $qty = array_key_exists($it->id, $overrideById)
                ? $overrideById[$it->id]
                : (int) $it->qty_separated;
            $out[$it->barcode] = ($out[$it->barcode] ?? 0) + $qty;
        }
        return $out;
    }

    /**
     * Busca metadata (descrição, ref, cor, tamanho) no catálogo por
     * lista de barcodes. Usado pra identificar items "extra" da NF que
     * não estão no remanejo — sem isso, a UI mostraria só o EAN bruto.
     *
     * Mesmo padrão do RelocationSuggestionService::lookupProducts:
     * JOIN via product_variants (não products.reference, que é SKU pai),
     * + product_sizes pra traduzir size_cigam_code em label legível.
     *
     * @param  array<int, string>  $barcodes
     * @return array<string, array{product_name: ?string, product_reference: ?string, size: ?string, product_color: ?string}>
     */
    protected function lookupCatalog(array $barcodes): array
    {
        $barcodes = array_values(array_filter($barcodes));
        if (empty($barcodes)) {
            return [];
        }

        $rows = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('product_colors as pc', 'pc.cigam_code', '=', 'p.color_cigam_code')
            ->leftJoin('product_sizes as ps', 'ps.cigam_code', '=', 'pv.size_cigam_code')
            ->where(function ($q) use ($barcodes) {
                $q->whereIn('pv.barcode', $barcodes)
                    ->orWhereIn('pv.aux_reference', $barcodes);
            })
            ->select([
                'pv.barcode',
                'pv.aux_reference',
                'pv.size_cigam_code',
                'p.reference',
                'p.description',
                'pc.name as color_name',
                'ps.name as size_label',
            ])
            ->get();

        $byKey = [];
        foreach ($rows as $r) {
            $entry = [
                'product_name' => $r->description,
                'product_reference' => $r->reference,
                'size' => $r->size_label ?? $r->size_cigam_code,
                'product_color' => $r->color_name,
            ];
            if ($r->barcode) $byKey[$r->barcode] = $entry;
            if ($r->aux_reference) $byKey[$r->aux_reference] = $entry;
        }

        return $byKey;
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
