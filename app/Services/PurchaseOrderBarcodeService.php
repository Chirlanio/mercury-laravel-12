<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderBarcode;
use Illuminate\Support\Facades\DB;

/**
 * Gera/recupera EAN-13 internos para itens de ordens de compra.
 *
 * Idempotente por (reference, size): se já existe um barcode para a
 * combinação, retorna o existente. Caso contrário, cria com o
 * EanGeneratorService usando o id da row como variant_id (garante
 * unicidade trivial e estabilidade pra mesma row).
 */
class PurchaseOrderBarcodeService
{
    public function __construct(
        protected EanGeneratorService $eanGenerator,
    ) {}

    /**
     * Garante que existe barcode para uma (reference, size) — cria se não
     * existe, retorna o existente caso contrário. NÃO modifica registros
     * já criados.
     */
    public function ensureFor(string $reference, string $size): PurchaseOrderBarcode
    {
        $existing = PurchaseOrderBarcode::where('reference', $reference)
            ->where('size', $size)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($reference, $size) {
            // Cria a row sem barcode primeiro pra obter o id
            $row = PurchaseOrderBarcode::create([
                'reference' => $reference,
                'size' => $size,
                'barcode' => '0000000000000', // placeholder, atualizado abaixo
            ]);

            // Usa o id da row como variant_id (sempre unique)
            // productId=0 indica "interno PO" (separa do catálogo de produtos
            // do Products module, que usa product_id real)
            $barcode = $this->eanGenerator->generate(0, $row->id);

            $row->update(['barcode' => $barcode]);

            return $row->fresh();
        });
    }

    /**
     * Gera/recupera barcodes para todos os itens de uma ordem.
     *
     * @return array{generated: int, existing: int}
     */
    public function ensureForOrder(PurchaseOrder $order): array
    {
        $generated = 0;
        $existing = 0;

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $before = PurchaseOrderBarcode::where('reference', $item->reference)
                ->where('size', $item->size)
                ->exists();

            $this->ensureFor($item->reference, $item->size);

            if ($before) {
                $existing++;
            } else {
                $generated++;
            }
        }

        return compact('generated', 'existing');
    }

    /**
     * Lookup em batch para uma coleção de items — retorna mapa
     * "reference|size" => barcode. Útil pra carregar barcodes na resposta
     * do detail sem N queries.
     *
     * @param  iterable<\App\Models\PurchaseOrderItem>  $items
     * @return array<string, string>
     */
    public function lookupForItems(iterable $items): array
    {
        $pairs = collect($items)->map(fn ($i) => [
            'reference' => $i->reference,
            'size' => $i->size,
        ])->unique(fn ($p) => $p['reference'] . '|' . $p['size'])->values();

        if ($pairs->isEmpty()) {
            return [];
        }

        $query = PurchaseOrderBarcode::query();
        foreach ($pairs as $pair) {
            $query->orWhere(function ($q) use ($pair) {
                $q->where('reference', $pair['reference'])
                    ->where('size', $pair['size']);
            });
        }

        return $query->get()
            ->mapWithKeys(fn ($b) => [$b->reference . '|' . $b->size => $b->barcode])
            ->all();
    }
}
