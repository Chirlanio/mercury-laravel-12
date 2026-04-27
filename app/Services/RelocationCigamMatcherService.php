<?php

namespace App\Services;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Casa NF de remanejos `in_transit` em DUAS PONTAS:
 *
 *  1. ORIGEM (saída): movement_code=5 + entry_exit='S' + store_code=origem
 *     + invoice_number. Confirma que a loja origem efetivamente despachou
 *     as referências/quantidades. Marca `cigam_dispatched_at` e agrega
 *     `dispatched_quantity` por barcode.
 *     Métrica derivada: ADERÊNCIA da origem (dispatched / requested).
 *
 *  2. DESTINO (entrada): movement_code=5 + entry_exit='E' + store_code=destino
 *     + invoice_number. Confirma que a loja destino recebeu a NF e processou.
 *     Marca `cigam_received_at` e agrega `received_quantity` por barcode.
 *     Métrica derivada: tempo de trânsito CIGAM (received_at - dispatched_at).
 *
 * NÃO transita o status — recebimento físico pela loja destino continua
 * sendo manual (com receiver_name). A reconciliação CIGAM é informativa
 * e alimenta o dashboard de aderência.
 *
 * Idempotente: cada ponta é casada uma única vez (pula se timestamp já setado).
 */
class RelocationCigamMatcherService
{
    /**
     * Janela de busca em movements (NF de transferência pode demorar
     * semanas pra refletir no CIGAM).
     */
    private const LOOKUP_DAYS = 60;

    /**
     * Roda o matcher pra todos os remanejos in_transit pendentes
     * (qualquer das duas pontas faltando).
     *
     * @return array{
     *   relocations_checked: int,
     *   dispatched_matched: int,
     *   received_matched: int,
     *   total_items_dispatched: int,
     *   total_items_received: int,
     * }
     */
    public function matchAllPending(): array
    {
        $pending = Relocation::query()
            ->pendingCigamMatch()
            ->with(['originStore:id,code', 'destinationStore:id,code', 'items'])
            ->get();

        $dispatchedMatched = 0;
        $receivedMatched = 0;
        $totalItemsDispatched = 0;
        $totalItemsReceived = 0;

        foreach ($pending as $relocation) {
            // Origem
            if (! $relocation->cigam_dispatched_at) {
                $items = $this->matchOriginDispatch($relocation);
                if ($items > 0) {
                    $dispatchedMatched++;
                    $totalItemsDispatched += $items;
                }
            }

            // Destino — recarrega pra pegar `cigam_dispatched_at` atualizado
            $relocation->refresh();
            if (! $relocation->cigam_received_at) {
                $items = $this->matchDestinationReceipt($relocation);
                if ($items > 0) {
                    $receivedMatched++;
                    $totalItemsReceived += $items;
                }
            }
        }

        return [
            'relocations_checked' => $pending->count(),
            'dispatched_matched' => $dispatchedMatched,
            'received_matched' => $receivedMatched,
            'total_items_dispatched' => $totalItemsDispatched,
            'total_items_received' => $totalItemsReceived,
        ];
    }

    /**
     * Match na ORIGEM — saída registrada no CIGAM. Indica aderência
     * da loja origem ao solicitado (separou tudo? em parte?).
     *
     * Retorna número de items com match (0 = NF ainda não apareceu).
     */
    public function matchOriginDispatch(Relocation $relocation): int
    {
        if (! $relocation->invoice_number || $relocation->cigam_dispatched_at) {
            return 0;
        }

        $originCode = $relocation->originStore?->code;
        if (! $originCode) {
            return 0;
        }

        return $this->matchSide(
            $relocation,
            storeCode: $originCode,
            entryExit: 'S',
            timestampField: 'cigam_dispatched_at',
            quantityField: 'dispatched_quantity',
            logTag: 'dispatched',
        );
    }

    /**
     * Match no DESTINO — entrada registrada no CIGAM. Confirma que a NF
     * chegou à loja destino e foi processada.
     */
    public function matchDestinationReceipt(Relocation $relocation): int
    {
        if (! $relocation->invoice_number || $relocation->cigam_received_at) {
            return 0;
        }

        $destCode = $relocation->destinationStore?->code;
        if (! $destCode) {
            return 0;
        }

        return $this->matchSide(
            $relocation,
            storeCode: $destCode,
            entryExit: 'E',
            timestampField: 'cigam_received_at',
            quantityField: 'received_quantity',
            logTag: 'received',
        );
    }

    /**
     * Lógica compartilhada das 2 pontas. Busca movements code=5 com o
     * entry_exit/store_code apropriado, agrega por barcode e atualiza os
     * itens + timestamp do remanejo em transação.
     */
    protected function matchSide(
        Relocation $relocation,
        string $storeCode,
        string $entryExit,
        string $timestampField,
        string $quantityField,
        string $logTag,
    ): int {
        $rows = DB::table('movements')
            ->where('movement_code', 5)
            ->where('entry_exit', $entryExit)
            ->where('store_code', $storeCode)
            ->where('invoice_number', $relocation->invoice_number)
            ->whereBetween('movement_date', [
                now()->subDays(self::LOOKUP_DAYS)->toDateString(),
                now()->addDays(1)->toDateString(),
            ])
            ->select(['barcode', DB::raw('SUM(quantity) as total_qty')])
            ->groupBy('barcode')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $byBarcode = $rows->keyBy('barcode');

        return DB::transaction(function () use ($relocation, $byBarcode, $timestampField, $quantityField, $logTag, $storeCode) {
            $itemsMatched = 0;

            foreach ($relocation->items as $item) {
                /** @var RelocationItem $item */
                if (! $item->barcode) {
                    continue;
                }

                $row = $byBarcode->get($item->barcode);
                if (! $row) {
                    continue;
                }

                $item->{$quantityField} = (int) $row->total_qty;
                $item->save();
                $itemsMatched++;
            }

            $relocation->update([$timestampField => now()]);

            Log::info("Relocation CIGAM matched ({$logTag})", [
                'relocation_id' => $relocation->id,
                'invoice_number' => $relocation->invoice_number,
                'store' => $storeCode,
                'side' => $logTag,
                'items_matched' => $itemsMatched,
            ]);

            return $itemsMatched;
        });
    }
}
