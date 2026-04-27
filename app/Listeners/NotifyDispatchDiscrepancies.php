<?php

namespace App\Listeners;

use App\Enums\Permission;
use App\Enums\RelocationStatus;
use App\Events\RelocationStatusChanged;
use App\Models\User;
use App\Notifications\RelocationDispatchDiscrepancyNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Auto-discovered (Laravel 12). NÃO registrar Event::listen manualmente —
 * gera handler duplicado.
 *
 * Filtra: só age quando a transição é IN_SEPARATION → IN_TRANSIT E o
 * snapshot da validação aponta divergências (dispatch_has_discrepancies).
 *
 * Destinatários:
 *  - criador do remanejo (quem solicitou)
 *  - usuários com APPROVE_RELOCATIONS (planejamento)
 *  - usuários com MANAGE_RELOCATIONS (logística cross-tenant / supervisor)
 *
 * Fail-safe: erros loggados em warning, não quebram a transição (que já
 * foi commitada quando o evento dispara).
 */
class NotifyDispatchDiscrepancies
{
    public function handle(RelocationStatusChanged $event): void
    {
        $relocation = $event->relocation;

        if ($event->toStatus !== RelocationStatus::IN_TRANSIT) {
            return;
        }

        if (! $relocation->dispatch_has_discrepancies) {
            return;
        }

        try {
            $relocation->loadMissing(['originStore', 'destinationStore']);

            $recipients = User::query()
                ->where('id', '!=', $event->actor->id)
                ->where(function ($q) use ($relocation) {
                    $q->where('id', $relocation->created_by_user_id)
                        ->orWhereHas('roles.permissions', function ($p) {
                            $p->whereIn('slug', [
                                Permission::APPROVE_RELOCATIONS->value,
                                Permission::MANAGE_RELOCATIONS->value,
                            ]);
                        });
                })
                ->get()
                ->unique('id');

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send(
                $recipients,
                new RelocationDispatchDiscrepancyNotification($relocation),
            );
        } catch (\Throwable $e) {
            Log::warning('NotifyDispatchDiscrepancies failed', [
                'relocation_id' => $relocation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
