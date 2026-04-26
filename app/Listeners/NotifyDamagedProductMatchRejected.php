<?php

namespace App\Listeners;

use App\Events\DamagedProductMatchRejected;
use App\Models\User;
use App\Notifications\DamagedProductMatchRejectedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notifica criadores dos 2 produtos avariados envolvidos quando o match
 * é rejeitado, pra que possam revisar a avaria/par trocado e ajustar (ou
 * aceitar a possibilidade de outro match futuro).
 *
 * Auto-discovered.
 */
class NotifyDamagedProductMatchRejected
{
    public function handle(DamagedProductMatchRejected $event): void
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
                Notification::send($users, new DamagedProductMatchRejectedNotification(
                    match: $match,
                    actor: $event->actor,
                    reason: $event->reason,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify damaged-product match rejected', [
                'match_id' => $event->match->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
