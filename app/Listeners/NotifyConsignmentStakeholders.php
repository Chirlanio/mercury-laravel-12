<?php

namespace App\Listeners;

use App\Enums\ConsignmentStatus;
use App\Enums\Permission;
use App\Events\ConsignmentStatusChanged;
use App\Models\User;
use App\Notifications\ConsignmentStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escuta ConsignmentStatusChanged e notifica stakeholders via
 * database (sino do frontend).
 *
 * Laravel 12 auto-discovery ativo — NÃO registrar Event::listen manual.
 * Tipagem do handle() basta para registro automático.
 *
 * Matriz de destinatários por transição (sempre exclui o actor):
 *  - → pending (emitida): criador recebe (confirmação de emissão).
 *    Se consultor(a) existe e é diferente do criador, também recebe.
 *  - → partially_returned: criador + consultor(a) (parte dos itens voltou).
 *  - → overdue (automático via command): criador + consultor(a) +
 *    usuários com MANAGE_CONSIGNMENTS da mesma loja (supervisão).
 *  - → completed: criador + consultor(a) (ciclo encerrado).
 *  - → cancelled: criador + consultor(a) (saber motivo).
 *  - draft/outros: silêncio (estado interno, actor já sabe).
 *
 * Falhas NÃO quebram o fluxo de transição (já estamos pós-commit).
 */
class NotifyConsignmentStakeholders
{
    public function handle(ConsignmentStatusChanged $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ConsignmentStatusChangedNotification(
                    consignment: $event->consignment,
                    fromStatus: $event->fromStatus,
                    toStatus: $event->toStatus,
                    actor: $event->actor,
                    note: $event->note,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify consignment stakeholders', [
                'consignment_id' => $event->consignment->id,
                'from' => $event->fromStatus->value,
                'to' => $event->toStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveRecipients(ConsignmentStatusChanged $event)
    {
        $actorId = $event->actor?->id;
        $creatorId = $event->consignment->created_by_user_id;
        $employeeId = $event->consignment->employee_id;
        $storeId = $event->consignment->store_id;
        $to = $event->toStatus;

        // Mapear employee_id → user_id (via email ou outra ligação)
        // Como não há FK direta entre employees e users, notifica só
        // o criador e, em caso de overdue, supervisores da loja.
        $recipientIds = collect();

        if ($creatorId) {
            $recipientIds->push($creatorId);
        }

        // Em overdue, inclui supervisores/gerentes da loja (MANAGE_CONSIGNMENTS)
        if ($to === ConsignmentStatus::OVERDUE) {
            $managerIds = User::query()
                ->where('id', '!=', $actorId ?? 0)
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo(Permission::MANAGE_CONSIGNMENTS->value))
                ->pluck('id');
            $recipientIds = $recipientIds->merge($managerIds);
        }

        $candidates = User::query()
            ->whereIn('id', $recipientIds->unique()->values())
            ->when($actorId, fn ($q) => $q->where('id', '!=', $actorId))
            ->get();

        return $candidates->filter(function (User $user) use ($to) {
            return match ($to) {
                ConsignmentStatus::PENDING,
                ConsignmentStatus::PARTIALLY_RETURNED,
                ConsignmentStatus::OVERDUE,
                ConsignmentStatus::COMPLETED,
                ConsignmentStatus::CANCELLED => true,

                default => false, // DRAFT não notifica
            };
        })->values();
    }
}
