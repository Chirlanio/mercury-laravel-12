<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Acesso a `msl_festoqueatual_` (view CIGAM PostgreSQL) — saldo real
 * por (loja, código de barras). Substitui o saldo-proxy histórico
 * (`+code=1 -code=2 ...`) usado anteriormente nas sugestões de remanejo.
 *
 * Estrutura da view:
 *   loja        VARCHAR  (código da loja: Z421, Z422, ...)
 *   cod_barra   VARCHAR  (referência interna — formato A0319902750001U35)
 *   refauxiliar VARCHAR  (EAN13 — 7900378681272)
 *   saldo       INTEGER  (qty atual em estoque, pode ser 0+)
 *
 * `cod_barra` aqui é o que chamamos de `barcode` no v2 (mesmo padrão de
 * `product_variants.barcode`). `refauxiliar` é o EAN13 também presente
 * em `product_variants.aux_reference`.
 *
 * Fail-safe: se CIGAM offline (pdo_pgsql ausente, conexão falha, env
 * vazio), retorna coleções/arrays vazios + Log::warning. Nunca quebra
 * o caller — quem consome trata `available > 0` naturalmente.
 *
 * Não cacheamos aqui — saldo muda em tempo real e a fonte de dados é
 * authoritative. Se a query ficar pesada com volume real, considerar
 * cache::store('array') TTL 60s no service consumer (Suggestions).
 */
class CigamStockService
{
    private const VIEW_NAME = 'msl_festoqueatual_';

    private ?bool $available = null;
    private ?string $unavailableReason = null;

    /**
     * Detecção de disponibilidade do CIGAM. Cache em propriedade —
     * decidida 1x por request.
     */
    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (! extension_loaded('pdo_pgsql')) {
            $this->unavailableReason = 'pdo_pgsql não disponível';
            return $this->available = false;
        }

        $host = config('database.connections.cigam.host');
        if (empty($host) || $host === '127.0.0.1') {
            if (empty(env('CIGAM_DB_HOST'))) {
                $this->unavailableReason = 'CIGAM_DB_* não configurado no .env';
                return $this->available = false;
            }
        }

        try {
            DB::connection('cigam')->getPdo();
            return $this->available = true;
        } catch (\Throwable $e) {
            $this->unavailableReason = $e->getMessage();
            Log::warning('CIGAM stock connection failed', ['error' => $e->getMessage()]);
            return $this->available = false;
        }
    }

    public function getUnavailableReason(): ?string
    {
        return $this->unavailableReason;
    }

    /**
     * Saldo de uma lista de barcodes em todas as lojas. Aceita matching
     * tanto por `cod_barra` quanto por `refauxiliar` (mesmo padrão do
     * lookup de produto via product_variants.barcode/aux_reference).
     *
     * Filtra `saldo > 0` por padrão — só retorna onde de fato tem peça.
     *
     * @param  array<int, string> $barcodes
     * @param  string|null $excludeStoreCode  Se setado, exclui esta loja
     *                                        (útil pra excluir o destino)
     * @param  array<int, string>|null $onlyStoreCodes  Se setado, restringe
     *                                                  às lojas listadas
     *                                                  (filtro de mesma rede)
     * @return Collection<int, object{store_code:string, cod_barra:string, refauxiliar:string, saldo:int}>
     */
    public function availableForBarcodes(
        array $barcodes,
        ?string $excludeStoreCode = null,
        ?array $onlyStoreCodes = null,
    ): Collection {
        if (empty($barcodes) || ! $this->isAvailable()) {
            return collect();
        }

        // Normaliza pra string e remove vazios
        $barcodes = array_values(array_unique(array_filter(
            array_map(fn ($b) => trim((string) $b), $barcodes),
            fn ($b) => $b !== ''
        )));

        if (empty($barcodes)) {
            return collect();
        }

        try {
            $query = DB::connection('cigam')
                ->table(self::VIEW_NAME)
                ->where(function ($q) use ($barcodes) {
                    $q->whereIn('cod_barra', $barcodes)
                        ->orWhereIn('refauxiliar', $barcodes);
                })
                ->where('saldo', '>', 0)
                ->select(
                    DB::raw('loja as store_code'),
                    'cod_barra',
                    'refauxiliar',
                    'saldo'
                );

            if ($excludeStoreCode) {
                $query->where('loja', '!=', $excludeStoreCode);
            }

            if (! empty($onlyStoreCodes)) {
                $query->whereIn('loja', $onlyStoreCodes);
            }

            return $query->get();
        } catch (\Throwable $e) {
            Log::warning('CIGAM stock query failed', [
                'error' => $e->getMessage(),
                'barcodes_count' => count($barcodes),
            ]);
            return collect();
        }
    }

    /**
     * Saldo de UM barcode em UMA loja. Útil pra validações pontuais.
     */
    public function availableForStore(string $storeCode, string $barcode): int
    {
        if (! $this->isAvailable()) return 0;

        try {
            $row = DB::connection('cigam')
                ->table(self::VIEW_NAME)
                ->where('loja', $storeCode)
                ->where(function ($q) use ($barcode) {
                    $q->where('cod_barra', $barcode)
                        ->orWhere('refauxiliar', $barcode);
                })
                ->select(DB::raw('SUM(saldo) as total'))
                ->first();

            return (int) ($row->total ?? 0);
        } catch (\Throwable $e) {
            Log::warning('CIGAM stock single-store query failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
