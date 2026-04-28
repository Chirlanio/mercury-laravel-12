<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Validation\ValidationException;

/**
 * Validação de saldo absoluto pra um conjunto de items de remanejo
 * contra a loja origem. Combina:
 *  - Saldo CIGAM real (via CigamStockService::availableForBarcodes)
 *  - Saldo já comprometido em outros remanejos abertos (via
 *    RelocationCommittedStockService) — desconta pra evitar overcommit
 *
 * Usado em 2 pontos do fluxo:
 *  1. RelocationService::create — valida na criação do DRAFT
 *  2. RelocationTransitionService — valida em →APPROVED (saldo pode ter
 *     mudado entre criação e aprovação por causa de vendas paralelas)
 *
 * Quando CIGAM offline ou origem não resolvida, pula silenciosamente
 * (melhor permitir do que bloquear arbitrariamente).
 */
class RelocationStockValidator
{
    public function __construct(
        protected CigamStockService $cigam,
        protected RelocationCommittedStockService $committed,
    ) {}

    /**
     * @param  array<int, array{barcode?: string|null, qty_requested?: int|string}>  $items
     * @throws ValidationException
     */
    public function validate(int $originStoreId, array $items, ?int $excludeRelocationId = null): void
    {
        if (empty($items) || ! $this->cigam->isAvailable()) {
            return;
        }

        $originCode = Store::where('id', $originStoreId)->value('code');
        if (! $originCode) {
            return;
        }

        // Soma qty_requested por barcode (caso o usuário tenha colocado o
        // mesmo produto em 2 linhas — raro, mas suportado).
        $byBarcode = [];
        foreach ($items as $it) {
            $bc = trim((string) ($it['barcode'] ?? ''));
            if ($bc === '') continue;
            $byBarcode[$bc] = ($byBarcode[$bc] ?? 0) + (int) ($it['qty_requested'] ?? 0);
        }
        if (empty($byBarcode)) {
            return;
        }

        $barcodes = array_keys($byBarcode);

        $stockRows = $this->cigam->availableForBarcodes(
            $barcodes,
            onlyStoreCodes: [$originCode],
        );
        $cigamByBarcode = [];
        foreach ($stockRows as $row) {
            $cigamByBarcode[$row->cod_barra] = ($cigamByBarcode[$row->cod_barra] ?? 0) + (int) $row->saldo;
            if (! empty($row->refauxiliar) && $row->refauxiliar !== $row->cod_barra) {
                $cigamByBarcode[$row->refauxiliar] = ($cigamByBarcode[$row->refauxiliar] ?? 0) + (int) $row->saldo;
            }
        }

        $committedByBarcode = $this->committed->committedByBarcode(
            $originStoreId,
            $barcodes,
            $excludeRelocationId,
        );

        $errors = [];
        foreach ($byBarcode as $bc => $req) {
            $cigamQty = $cigamByBarcode[$bc] ?? 0;
            $reservedQty = $committedByBarcode[$bc] ?? 0;
            $effective = max(0, $cigamQty - $reservedQty);
            if ($req > $effective) {
                if ($reservedQty > 0) {
                    $errors[] = "Produto {$bc}: solicitado {$req} unidades, mas saldo efetivo é {$effective} "
                        ."(CIGAM tem {$cigamQty}, sendo {$reservedQty} já reservado(s) em outro(s) remanejo(s) aberto(s) da mesma loja).";
                } else {
                    $errors[] = "Produto {$bc}: solicitado {$req} unidades, mas saldo CIGAM é {$cigamQty}.";
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages([
                'items' => $errors,
            ]);
        }
    }
}
