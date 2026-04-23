<?php

namespace App\Jobs;

use App\Services\CustomerSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Job que executa a sincronização completa dos clientes a partir da
 * view CIGAM msl_dcliente_.
 *
 * Proposital: NÃO implementa ShouldQueue — isso permite usar
 * `dispatchAfterResponse()` no controller, que roda o job no MESMO
 * processo PHP mas DEPOIS da resposta HTTP ser enviada ao cliente.
 *
 * Vantagens para o UX de sync:
 *  - Browser recebe o redirect imediatamente, não fica pendurado
 *  - Session lock é liberada assim que a resposta sai
 *  - Usuário pode navegar para outras páginas enquanto o sync roda
 *  - Não depende de queue worker externo (composer dev ou equivalente)
 *
 * Limitação: se o PHP-FPM/artisan serve morrer no meio, o sync para
 * na linha que estava. O próximo `customers:sync` via CLI (ou clique
 * manual) retoma de onde parou (upsert idempotente por cigam_code).
 */
class SyncCustomersFromCigamJob
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly int $logId,
        public readonly int $chunkSize = 1000,
    ) {
    }

    public function handle(CustomerSyncService $service): void
    {
        // Sem limite de tempo — background, não amarrado ao HTTP timeout
        set_time_limit(0);

        $lastCode = null;

        try {
            while (true) {
                $result = $service->processChunk($this->logId, $lastCode, $this->chunkSize);
                $lastCode = $result['last_code'];

                if (! $result['has_more'] || $result['cancelled']) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::error('SyncCustomersFromCigamJob failed', [
                'log_id' => $this->logId,
                'last_code' => $lastCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // O service já marca o log como failed quando o processChunk
            // explode. Aqui só registramos no log do Laravel para diagnóstico.
        }
    }
}
