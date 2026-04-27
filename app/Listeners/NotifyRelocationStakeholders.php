<?php

namespace App\Listeners;

use App\Enums\Permission;
use App\Enums\RelocationStatus;
use App\Events\RelocationStatusChanged;
use App\Models\Store;
use App\Models\User;
use App\Notifications\RelocationStatusChangedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escuta RelocationStatusChanged e notifica stakeholders via database
 * notification (sino do frontend).
 *
 * Auto-discovered via type-hint do `handle(RelocationStatusChanged $e)`
 * — NÃO registrar via Event::listen manual (Laravel 12 dispara em duplicidade).
 *
 * Matriz de destinatários por transição (excluindo sempre o actor):
 *
 *  - → REQUESTED (solicitação de aprovação)
 *      → APPROVE_RELOCATIONS (planejamento + admin) + criador
 *
 *  - → APPROVED (planejamento aprovou)
 *      → criador + SEPARATE_RELOCATIONS na loja origem (gerentes)
 *
 *  - → IN_SEPARATION (separação iniciada)
 *      → criador (informativo)
 *
 *  - → IN_TRANSIT (NF informada, fardo despachado)
 *      → RECEIVE_RELOCATIONS na loja destino (gerentes) + criador
 *
 *  - → COMPLETED / PARTIAL (recebido)
 *      → criador + APPROVE_RELOCATIONS (planejamento sabe que fechou)
 *
 *  - → REJECTED (planejamento negou)
 *      → criador (vê motivo)
 *
 *  - → CANCELLED (cancelado em qualquer estado pré-trânsito)
 *      → criador + APPROVE_RELOCATIONS
 *
 * Falhas no envio NÃO devem quebrar o fluxo de transição (já estamos
 * pós-commit do banco).
 */
class NotifyRelocationStakeholders
{
    public function handle(RelocationStatusChanged $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new RelocationStatusChangedNotification(
                relocation: $event->relocation,
                fromStatus: $event->fromStatus,
                toStatus: $event->toStatus,
                actor: $event->actor,
                note: $event->note,
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify relocation stakeholders', [
                'relocation_id' => $event->relocation->id,
                'from' => $event->fromStatus->value,
                'to' => $event->toStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveRecipients(RelocationStatusChanged $event): Collection
    {
        $actorId = $event->actor->id;
        $creatorId = $event->relocation->created_by_user_id;
        $originStoreCode = $event->relocation->originStore?->code;
        $destinationStoreCode = $event->relocation->destinationStore?->code;
        $to = $event->toStatus;

        // Para evitar carregar todos users e filtrar em memória, monta query
        // dinâmica conforme o destino.
        $query = User::query()->where('id', '!=', $actorId);

        switch ($to) {
            case RelocationStatus::REQUESTED:
                // Quem aprova + criador
                $query->where(function ($q) use ($creatorId) {
                    $q->where('id', $creatorId)
                        ->orWhereHas('roles.permissions', fn ($p) => $p->where('slug', Permission::APPROVE_RELOCATIONS->value));
                });
                break;

            case RelocationStatus::APPROVED:
                // Criador + quem separa na loja origem (gerentes)
                $query->where(function ($q) use ($creatorId, $originStoreCode) {
                    $q->where('id', $creatorId)
                        ->orWhere(function ($q2) use ($originStoreCode) {
                            $q2->where('store_id', $originStoreCode)
                                ->whereHas('roles.permissions', fn ($p) => $p->where('slug', Permission::SEPARATE_RELOCATIONS->value));
                        });
                });
                break;

            case RelocationStatus::IN_SEPARATION:
                // Apenas criador (informativo)
                $query->where('id', $creatorId);
                break;

            case RelocationStatus::IN_TRANSIT:
                // Criador + quem recebe na loja destino (gerentes)
                $query->where(function ($q) use ($creatorId, $destinationStoreCode) {
                    $q->where('id', $creatorId)
                        ->orWhere(function ($q2) use ($destinationStoreCode) {
                            $q2->where('store_id', $destinationStoreCode)
                                ->whereHas('roles.permissions', fn ($p) => $p->where('slug', Permission::RECEIVE_RELOCATIONS->value));
                        });
                });
                break;

            case RelocationStatus::COMPLETED:
            case RelocationStatus::PARTIAL:
                // Criador + planejamento (visão de fechamento)
                $query->where(function ($q) use ($creatorId) {
                    $q->where('id', $creatorId)
                        ->orWhereHas('roles.permissions', fn ($p) => $p->where('slug', Permission::APPROVE_RELOCATIONS->value));
                });
                break;

            case RelocationStatus::REJECTED:
                // Apenas criador (com motivo)
                $query->where('id', $creatorId);
                break;

            case RelocationStatus::CANCELLED:
                // Criador + planejamento
                $query->where(function ($q) use ($creatorId) {
                    $q->where('id', $creatorId)
                        ->orWhereHas('roles.permissions', fn ($p) => $p->where('slug', Permission::APPROVE_RELOCATIONS->value));
                });
                break;

            default:
                return collect();
        }

        return $query->get();
    }
}
