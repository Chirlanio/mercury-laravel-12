<?php

namespace App\Listeners;

use App\Enums\Permission;
use App\Events\DamagedProductMatchAccepted;
use App\Models\User;
use App\Notifications\DamagedProductMatchAcceptedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notifica criadores dos 2 produtos avariados envolvidos quando o match é
 * aceito (Transfer já criada). Não dispara mail — só DB (sino) pra não
 * inundar caixa postal a cada aceite.
 *
 * Auto-discovered — NÃO registrar manualmente em provider.
 */
class NotifyDamagedProductMatchAccepted
{
    public function handle(DamagedProductMatchAccepted $event): void
    {
        try {
            $match = $event->match;
            $actorId = $event->actor->id;

            $userIds = collect([
                $match->productA?->created_by_user_id,
                $match->productB?->created_by_user_id,
            ])
                ->filter()
                ->unique()
                ->reject(fn ($id) => $id === $actorId)
                ->values();

            if ($userIds->isEmpty()) {
                return;
            }

            $users = User::query()
                ->whereIn('id', $userIds)
                ->whereNotNull('email')
                ->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new DamagedProductMatchAcceptedNotification(
                    match: $match,
                    transfer: $event->transfer,
                    actor: $event->actor,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify damaged-product match accepted', [
                'match_id' => $event->match->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
