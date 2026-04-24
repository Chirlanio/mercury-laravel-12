<?php

namespace App\Services;

use App\Enums\ConsignmentStatus;
use App\Enums\Permission;
use App\Events\ConsignmentStatusChanged;
use App\Models\Consignment;
use App\Models\ConsignmentStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de consignações. Ponto único de mutação de
 * Consignment::status. Outros serviços e controllers NUNCA devem setar
 * o campo direto.
 *
 * Transições válidas (ConsignmentStatus::allowedTransitions):
 *   draft → pending | cancelled
 *   pending → partially_returned | overdue | completed | cancelled
 *   partially_returned → overdue | completed | cancelled
 *   overdue → partially_returned | completed | cancelled
 *   completed → [] (terminal)
 *   cancelled → [] (terminal)
 *
 * Permissões por transição:
 *  - draft → pending: exige CREATE_CONSIGNMENTS (autor emite NF)
 *  - * → overdue: automática (sem actor) via command consignments:mark-overdue
 *  - * → completed: exige COMPLETE_CONSIGNMENT
 *  - * → cancelled: exige CANCEL_CONSIGNMENT + note obrigatório
 *  - pending/overdue → partially_returned: exige REGISTER_CONSIGNMENT_RETURN
 *    (normalmente chamado pelo ConsignmentReturnService após lançar NF retorno)
 *  - overdue → partially_returned (retorno tardio): mesma permissão acima
 *
 * Laravel 12 auto-discovery: NÃO registrar Event::listen manualmente.
 * Listeners tipados em handle(ConsignmentStatusChanged $e) bastam.
 * Eventos serão adicionados na Fase 4.
 */
class ConsignmentTransitionService
{
    /**
     * @param  array<string, mixed>|null  $context
     *
     * @throws ValidationException
     */
    public function transition(
        Consignment $consignment,
        ConsignmentStatus|string $toStatus,
        ?User $actor,
        ?string $note = null,
        ?array $context = null,
    ): Consignment {
        if ($consignment->is_deleted) {
            throw ValidationException::withMessages([
                'consignment' => 'Não é possível transicionar uma consignação excluída.',
            ]);
        }

        $target = $toStatus instanceof ConsignmentStatus
            ? $toStatus
            : ConsignmentStatus::from($toStatus);
        $current = $consignment->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        // Finalização exige ao menos um retorno registrado.
        // ConsignmentReturnService::register cria o Return dentro da mesma transação
        // antes de chamar transition(), então essa regra não bloqueia o fluxo automático.
        if ($target === ConsignmentStatus::COMPLETED && ! $consignment->returns()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Uma consignação só pode ser finalizada após o registro de pelo menos um retorno.',
            ]);
        }

        // Transições automáticas (sem actor) permitidas apenas para OVERDUE
        // (command consignments:mark-overdue roda diariamente sem usuário).
        $canRunWithoutActor = $target === ConsignmentStatus::OVERDUE;

        if ($actor === null) {
            if (! $canRunWithoutActor) {
                throw ValidationException::withMessages([
                    'actor' => 'Usuário responsável pela transição é obrigatório.',
                ]);
            }
        } else {
            $this->authorizeTransition($current, $target, $actor);
        }

        if ($target === ConsignmentStatus::CANCELLED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'É obrigatório informar o motivo do cancelamento.',
            ]);
        }

        return DB::transaction(function () use ($consignment, $current, $target, $actor, $note, $context) {
            $update = [
                'status' => $target->value,
            ];

            if ($actor) {
                $update['updated_by_user_id'] = $actor->id;
            }

            if ($target === ConsignmentStatus::PENDING && $current === ConsignmentStatus::DRAFT) {
                $update['issued_at'] = now();
            }

            if ($target === ConsignmentStatus::COMPLETED) {
                $update['completed_at'] = now();
                if ($actor) {
                    $update['completed_by_user_id'] = $actor->id;
                }
            }

            if ($target === ConsignmentStatus::CANCELLED) {
                $update['cancelled_at'] = now();
                $update['cancelled_reason'] = $note;
            }

            $consignment->update($update);

            ConsignmentStatusHistory::create([
                'consignment_id' => $consignment->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor?->id,
                'note' => $note,
                'context' => $context,
                'created_at' => now(),
            ]);

            $fresh = $consignment->fresh(['items', 'returns', 'statusHistory']);

            // Dispatch pós-commit. Listeners (auto-discovery) reagem
            // sem quebrar a transação se falharem.
            ConsignmentStatusChanged::dispatch($fresh, $current, $target, $actor, $note);

            return $fresh;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function authorizeTransition(
        ConsignmentStatus $from,
        ConsignmentStatus $to,
        User $actor,
    ): void {
        // Cancelamento — CANCEL_CONSIGNMENT ou MANAGE_CONSIGNMENTS
        if ($to === ConsignmentStatus::CANCELLED) {
            $canCancel = $actor->hasPermissionTo(Permission::CANCEL_CONSIGNMENT->value)
                || $actor->hasPermissionTo(Permission::MANAGE_CONSIGNMENTS->value);

            if (! $canCancel) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para cancelar consignações.',
                ]);
            }

            return;
        }

        // Finalização — COMPLETE_CONSIGNMENT
        if ($to === ConsignmentStatus::COMPLETED) {
            if (! $actor->hasPermissionTo(Permission::COMPLETE_CONSIGNMENT->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para finalizar consignações.',
                ]);
            }

            return;
        }

        // Emissão de NF (draft → pending) — CREATE ou EDIT ou MANAGE
        if ($from === ConsignmentStatus::DRAFT && $to === ConsignmentStatus::PENDING) {
            $canIssue = $actor->hasPermissionTo(Permission::CREATE_CONSIGNMENTS->value)
                || $actor->hasPermissionTo(Permission::EDIT_CONSIGNMENTS->value)
                || $actor->hasPermissionTo(Permission::MANAGE_CONSIGNMENTS->value);

            if (! $canIssue) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para emitir consignação.',
                ]);
            }

            return;
        }

        // Retorno (→ partially_returned) — REGISTER_CONSIGNMENT_RETURN
        if ($to === ConsignmentStatus::PARTIALLY_RETURNED) {
            if (! $actor->hasPermissionTo(Permission::REGISTER_CONSIGNMENT_RETURN->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para lançar retornos de consignação.',
                ]);
            }

            return;
        }

        // Overdue só pode ser disparado automaticamente (actor null já
        // tratado acima). Chegar aqui com actor significa tentativa
        // manual indevida.
        if ($to === ConsignmentStatus::OVERDUE) {
            if (! $actor->hasPermissionTo(Permission::MANAGE_CONSIGNMENTS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Atraso é marcado automaticamente; ação manual exige MANAGE_CONSIGNMENTS.',
                ]);
            }

            return;
        }
    }

    /**
     * Convenience — emite NF de saída (draft → pending).
     */
    public function issue(Consignment $c, User $actor, ?string $note = null): Consignment
    {
        return $this->transition($c, ConsignmentStatus::PENDING, $actor, $note);
    }

    /**
     * Convenience — finaliza consignação (→ completed).
     */
    public function complete(Consignment $c, User $actor, ?string $note = null): Consignment
    {
        return $this->transition($c, ConsignmentStatus::COMPLETED, $actor, $note);
    }

    /**
     * Convenience — cancela consignação (→ cancelled). Motivo obrigatório.
     */
    public function cancel(Consignment $c, User $actor, string $reason): Consignment
    {
        return $this->transition($c, ConsignmentStatus::CANCELLED, $actor, $reason);
    }

    /**
     * Convenience — marca como atrasada automaticamente (command).
     */
    public function markOverdue(Consignment $c): Consignment
    {
        return $this->transition(
            $c,
            ConsignmentStatus::OVERDUE,
            null,
            'Prazo de retorno vencido',
            ['auto' => true, 'command' => 'consignments:mark-overdue'],
        );
    }
}
