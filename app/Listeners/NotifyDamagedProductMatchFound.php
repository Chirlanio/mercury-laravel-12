<?php

namespace App\Listeners;

use App\Enums\Permission;
use App\Events\DamagedProductMatchFound;
use App\Models\User;
use App\Notifications\DamagedProductMatchFoundNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escuta DamagedProductMatchFound e notifica quem tem APPROVE_MATCHES nas
 * lojas envolvidas (origem + destino sugeridos), via DB (sino) sempre + mail
 * apenas pra gerentes da loja DESTINO (ela toma a decisão de receber).
 *
 * Falha não quebra a engine — apenas loga warning.
 *
 * Auto-discovery do Laravel 12 registra esta classe automaticamente —
 * NÃO chamar Event::listen() manualmente (causaria duplicação).
 */
class NotifyDamagedProductMatchFound
{
    public function handle(DamagedProductMatchFound $event): void
    {
        try {
            $match = $event->match;
            $originCode = $match->suggestedOriginStore?->code;
            $destinationCode = $match->suggestedDestinationStore?->code;

            if (! $originCode && ! $destinationCode) {
                return;
            }

            // Pega todos usuários das 2 lojas envolvidas e filtra por permission.
            // MANAGE_DAMAGED_PRODUCTS está em SUPER_ADMIN/ADMIN/SUPPORT — esses
            // não dependem de store_id (vêem tudo) e seriam ruidosos pra
            // notificar em CADA match. Fica restrito aos APPROVE_MATCHES das
            // lojas diretamente envolvidas.
            $candidates = User::query()
                ->whereNotNull('email')
                ->whereIn('store_id', array_filter([$originCode, $destinationCode]))
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo(Permission::APPROVE_DAMAGED_PRODUCT_MATCHES->value))
                ->unique('id')
                ->values();

            if ($candidates->isEmpty()) {
                return;
            }

            // Mail só para destino (eles aceitam/rejeitam)
            $destinationUsers = $candidates->filter(fn (User $u) => $u->store_id === $destinationCode);
            $originOnly = $candidates->filter(fn (User $u) => ! $destinationUsers->contains('id', $u->id));

            if ($destinationUsers->isNotEmpty()) {
                Notification::send($destinationUsers, new DamagedProductMatchFoundNotification($match, sendMail: true));
            }
            if ($originOnly->isNotEmpty()) {
                Notification::send($originOnly, new DamagedProductMatchFoundNotification($match, sendMail: false));
            }

            $match->update(['notified_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify damaged-product match found', [
                'match_id' => $event->match->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
