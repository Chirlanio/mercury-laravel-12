<?php

namespace App\Listeners;

use App\Events\RelocationStatusBroadcast;
use App\Events\RelocationStatusChanged;
use Illuminate\Support\Facades\Log;

/**
 * Re-emite o RelocationStatusChanged como RelocationStatusBroadcast
 * (que é ShouldBroadcastNow → vai pro Reverb e atualiza UIs em tempo
 * real). Separação intencional pra que outros listeners (notification,
 * helpdesk) não dependam do canal Reverb estar online.
 *
 * Auto-discovered via type-hint do `handle(RelocationStatusChanged $e)`
 * — NÃO registrar via Event::listen manual.
 *
 * Fail-safe: se Reverb estiver offline ou o broadcast falhar, loga em
 * warning e segue. Notifications e helpdesk hooks já cobrem o histórico
 * persistente; Reverb é só o "ping" em tempo real.
 */
class BroadcastRelocationStatus
{
    public function handle(RelocationStatusChanged $event): void
    {
        try {
            RelocationStatusBroadcast::dispatch(
                $event->relocation,
                $event->fromStatus,
                $event->toStatus,
                $event->actor->name ?? null,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast relocation status', [
                'relocation_id' => $event->relocation->id,
                'to_status' => $event->toStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
