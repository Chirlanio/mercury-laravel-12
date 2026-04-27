<?php

namespace App\Services;

use App\Enums\RelocationStatus;
use Illuminate\Support\Facades\DB;

/**
 * Calcula saldo "comprometido" — itens que estão em remanejos abertos da
 * loja origem mas ainda não baixados no CIGAM. Sem isso, o saldo CIGAM
 * mostra o produto como disponível e múltiplos remanejos podem solicitar
 * a mesma unidade pra destinos diferentes (overcommit).
 *
 * Status que comprometem: DRAFT, REQUESTED, APPROVED, IN_SEPARATION
 * (definido em RelocationStatus::committingStock()).
 *
 * IN_TRANSIT é excluído pq nesse estágio a NF de saída já foi emitida e
 * o CIGAM já reflete a baixa — incluir geraria desconto duplo.
 *
 * Comprometimento por item = max(qty_requested - qty_separated, 0). Quando
 * a separação acontece e qty_separated é incrementado, o comprometimento
 * cai naturalmente até zero.
 */
class RelocationCommittedStockService
{
    /**
     * Soma comprometimento por (origin_store_id, barcode) em uma única query.
     *
     * @param  array<int, int>  $originStoreIds
     * @param  array<int, string>  $barcodes
     * @param  int|null  $excludeRelocationId  Útil em edição: não conta o
     *   próprio remanejo como comprometido contra ele mesmo.
     * @return array<string, int>  Indexado por "{origin_store_id}|{barcode}"
     */
    public function committedByStoreAndBarcode(
        array $originStoreIds,
        array $barcodes,
        ?int $excludeRelocationId = null,
    ): array {
        if (empty($originStoreIds) || empty($barcodes)) {
            return [];
        }

        $statuses = array_map(
            fn (RelocationStatus $s) => $s->value,
            RelocationStatus::committingStock(),
        );

        $query = DB::table('relocation_items as ri')
            ->join('relocations as r', 'r.id', '=', 'ri.relocation_id')
            ->whereIn('r.status', $statuses)
            ->whereNull('r.deleted_at')
            ->whereIn('r.origin_store_id', $originStoreIds)
            ->whereIn('ri.barcode', $barcodes);

        if ($excludeRelocationId !== null) {
            $query->where('r.id', '!=', $excludeRelocationId);
        }

        $rows = $query
            ->groupBy('r.origin_store_id', 'ri.barcode')
            ->selectRaw('
                r.origin_store_id,
                ri.barcode,
                SUM(CASE WHEN (ri.qty_requested - ri.qty_separated) > 0
                         THEN (ri.qty_requested - ri.qty_separated)
                         ELSE 0 END) as committed
            ')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->origin_store_id.'|'.$row->barcode] = (int) $row->committed;
        }

        return $result;
    }

    /**
     * Atalho pra UMA loja origem — devolve mapa barcode → comprometido.
     *
     * @param  array<int, string>  $barcodes
     * @return array<string, int>
     */
    public function committedByBarcode(
        int $originStoreId,
        array $barcodes,
        ?int $excludeRelocationId = null,
    ): array {
        $full = $this->committedByStoreAndBarcode([$originStoreId], $barcodes, $excludeRelocationId);

        $result = [];
        foreach ($full as $key => $qty) {
            [, $barcode] = explode('|', $key, 2);
            $result[$barcode] = $qty;
        }

        return $result;
    }
}
