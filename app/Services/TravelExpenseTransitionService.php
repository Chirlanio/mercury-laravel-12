<?php

namespace App\Services;

use App\Enums\AccountabilityStatus;
use App\Enums\Permission;
use App\Enums\TravelExpenseStatus;
use App\Events\TravelExpenseStatusChanged;
use App\Models\TravelExpense;
use App\Models\TravelExpenseStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de verbas de viagem. Ponto único de mutação dos campos
 * `status` (TravelExpenseStatus) e `accountability_status`
 * (AccountabilityStatus). Outros services e controllers NUNCA devem
 * setar esses campos diretamente.
 *
 * Transições da SOLICITAÇÃO (TravelExpenseStatus):
 *   draft → submitted | cancelled
 *   submitted → draft (devolver) | approved | rejected | cancelled
 *   approved → finalized | cancelled
 *   rejected/finalized/cancelled → terminal (nada)
 *
 * Permissões por transição (validadas em authorizeExpenseTransition):
 *  - draft → submitted: criador (CREATE_TRAVEL_EXPENSES) ou EDIT
 *  - submitted → approved/rejected: APPROVE_TRAVEL_EXPENSES
 *  - submitted → draft (voltar): APPROVE ou criador
 *  - approved → finalized: APPROVE (após accountability approved)
 *  - * → cancelled: APPROVE ou MANAGE
 *
 * Side-effects ao transitar:
 *  - submitted: grava submitted_at + valida payment info
 *  - approved: grava approved_at + approver_user_id + abre accountability
 *  - rejected: grava rejected_at + rejection_reason (obrigatório)
 *  - finalized: grava finalized_at (exige accountability_status=approved)
 *  - cancelled: grava cancelled_at + cancelled_reason (obrigatório)
 *
 * Transições da PRESTAÇÃO (AccountabilityStatus):
 *   pending → in_progress (auto, ao adicionar primeiro item)
 *   in_progress → pending (auto, ao remover último item) | submitted
 *   submitted → in_progress (rejeitar, devolver) | approved | rejected
 *   rejected → in_progress
 *
 * Permissões por transição:
 *  - in_progress → submitted: MANAGE_ACCOUNTABILITY (criador, beneficiado)
 *  - submitted → approved/rejected: APPROVE_TRAVEL_EXPENSES
 *  - voltas: APPROVE ou criador
 */
class TravelExpenseTransitionService
{
    // ==================================================================
    // SOLICITAÇÃO
    // ==================================================================

    /**
     * @throws ValidationException
     */
    public function transitionExpense(
        TravelExpense $te,
        TravelExpenseStatus|string $toStatus,
        User $actor,
        ?string $note = null
    ): TravelExpense {
        if ($te->is_deleted) {
            throw ValidationException::withMessages([
                'travel_expense' => 'Não é possível transicionar uma verba excluída.',
            ]);
        }

        $target = $toStatus instanceof TravelExpenseStatus
            ? $toStatus
            : TravelExpenseStatus::from($toStatus);
        $current = $te->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        $this->authorizeExpenseTransition($current, $target, $te, $actor);
        $this->validateExpenseTransitionPreconditions($te, $target, $note);

        return DB::transaction(function () use ($te, $current, $target, $actor, $note) {
            $update = [
                'status' => $target->value,
                'updated_by_user_id' => $actor->id,
            ];

            match ($target) {
                TravelExpenseStatus::SUBMITTED => $update['submitted_at'] = now(),
                TravelExpenseStatus::APPROVED => $this->fillApprovalFields($update, $actor, $te),
                TravelExpenseStatus::REJECTED => $this->fillRejectionFields($update, $note),
                TravelExpenseStatus::FINALIZED => $update['finalized_at'] = now(),
                TravelExpenseStatus::CANCELLED => $this->fillCancellationFields($update, $note),
                default => null,
            };

            $te->update($update);

            TravelExpenseStatusHistory::create([
                'travel_expense_id' => $te->id,
                'kind' => TravelExpenseStatusHistory::KIND_EXPENSE,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            $fresh = $te->fresh(['employee', 'store', 'bank', 'pixType', 'items', 'statusHistory']);

            // Eventos disparam listeners pra notificações + integrações.
            // Mantemos service agnóstico de side-effects.
            if (class_exists(TravelExpenseStatusChanged::class)) {
                TravelExpenseStatusChanged::dispatch($fresh, $current, $target, $actor, $note, 'expense');
            }

            return $fresh;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function authorizeExpenseTransition(
        TravelExpenseStatus $from,
        TravelExpenseStatus $to,
        TravelExpense $te,
        User $actor
    ): void {
        $isManager = $actor->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value);
        $isApprover = $actor->hasPermissionTo(Permission::APPROVE_TRAVEL_EXPENSES->value);
        $isOwner = $te->created_by_user_id === $actor->id;

        // Cancelamento — APPROVE ou MANAGE (qualquer fonte)
        if ($to === TravelExpenseStatus::CANCELLED) {
            if (! $isApprover && ! $isManager && ! ($isOwner && $from === TravelExpenseStatus::DRAFT)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para cancelar verbas.',
                ]);
            }

            return;
        }

        // draft → submitted: criador com CREATE/EDIT
        if ($from === TravelExpenseStatus::DRAFT && $to === TravelExpenseStatus::SUBMITTED) {
            $allowed = $isOwner || $isManager
                || $actor->hasPermissionTo(Permission::EDIT_TRAVEL_EXPENSES->value);

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para enviar esta verba para aprovação.',
                ]);
            }

            return;
        }

        // submitted → draft (voltar): criador ou aprovador
        if ($from === TravelExpenseStatus::SUBMITTED && $to === TravelExpenseStatus::DRAFT) {
            if (! $isOwner && ! $isApprover && ! $isManager) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para devolver verba para Rascunho.',
                ]);
            }

            return;
        }

        // submitted → approved/rejected: APPROVE
        if ($from === TravelExpenseStatus::SUBMITTED
            && in_array($to, [TravelExpenseStatus::APPROVED, TravelExpenseStatus::REJECTED], true)) {
            if (! $isApprover && ! $isManager) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para aprovar/rejeitar verbas.',
                ]);
            }

            return;
        }

        // approved → finalized: APPROVE
        if ($from === TravelExpenseStatus::APPROVED && $to === TravelExpenseStatus::FINALIZED) {
            if (! $isApprover && ! $isManager) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para finalizar verbas.',
                ]);
            }

            return;
        }
    }

    /**
     * @throws ValidationException
     */
    protected function validateExpenseTransitionPreconditions(
        TravelExpense $te,
        TravelExpenseStatus $target,
        ?string $note
    ): void {
        if ($target === TravelExpenseStatus::SUBMITTED) {
            $hasBank = $te->bank_id && $te->bank_branch && $te->bank_account;
            $hasPix = $te->pix_type_id && $te->pix_key;

            if (! $hasBank && ! $hasPix) {
                throw ValidationException::withMessages([
                    'payment' => 'Informe ao menos uma forma de pagamento (dados bancários completos OU chave PIX) antes de enviar para aprovação.',
                ]);
            }
        }

        if ($target === TravelExpenseStatus::REJECTED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'Informe o motivo da rejeição.',
            ]);
        }

        if ($target === TravelExpenseStatus::CANCELLED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'Informe o motivo do cancelamento.',
            ]);
        }

        if ($target === TravelExpenseStatus::FINALIZED
            && $te->accountability_status !== AccountabilityStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Verba só pode ser finalizada após a prestação de contas ser aprovada.',
            ]);
        }
    }

    protected function fillApprovalFields(array &$update, User $actor, TravelExpense $te): void
    {
        $update['approved_at'] = now();
        $update['approver_user_id'] = $actor->id;
    }

    protected function fillRejectionFields(array &$update, ?string $note): void
    {
        $update['rejected_at'] = now();
        $update['rejection_reason'] = $note;
    }

    protected function fillCancellationFields(array &$update, ?string $note): void
    {
        $update['cancelled_at'] = now();
        $update['cancelled_reason'] = $note;
    }

    // ==================================================================
    // PRESTAÇÃO DE CONTAS
    // ==================================================================

    /**
     * @throws ValidationException
     */
    public function transitionAccountability(
        TravelExpense $te,
        AccountabilityStatus|string $toStatus,
        User $actor,
        ?string $note = null
    ): TravelExpense {
        if ($te->is_deleted) {
            throw ValidationException::withMessages([
                'travel_expense' => 'Não é possível transicionar uma verba excluída.',
            ]);
        }

        $target = $toStatus instanceof AccountabilityStatus
            ? $toStatus
            : AccountabilityStatus::from($toStatus);
        $current = $te->accountability_status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'accountability_status' => "Transição de prestação inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        $this->authorizeAccountabilityTransition($current, $target, $te, $actor);
        $this->validateAccountabilityPreconditions($te, $target, $note);

        return DB::transaction(function () use ($te, $current, $target, $actor, $note) {
            $update = [
                'accountability_status' => $target->value,
                'updated_by_user_id' => $actor->id,
            ];

            match ($target) {
                AccountabilityStatus::SUBMITTED => $update['accountability_submitted_at'] = now(),
                AccountabilityStatus::APPROVED => $update['accountability_approved_at'] = now(),
                AccountabilityStatus::REJECTED => $this->fillAccountabilityRejection($update, $note),
                default => null,
            };

            $te->update($update);

            TravelExpenseStatusHistory::create([
                'travel_expense_id' => $te->id,
                'kind' => TravelExpenseStatusHistory::KIND_ACCOUNTABILITY,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            $fresh = $te->fresh(['employee', 'items', 'statusHistory']);

            if (class_exists(TravelExpenseStatusChanged::class)) {
                TravelExpenseStatusChanged::dispatch($fresh, $current, $target, $actor, $note, 'accountability');
            }

            return $fresh;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function authorizeAccountabilityTransition(
        AccountabilityStatus $from,
        AccountabilityStatus $to,
        TravelExpense $te,
        User $actor
    ): void {
        $isManager = $actor->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value);
        $isApprover = $actor->hasPermissionTo(Permission::APPROVE_TRAVEL_EXPENSES->value);
        $canManageAccountability = $actor->hasPermissionTo(Permission::MANAGE_ACCOUNTABILITY->value);
        $isOwner = $te->created_by_user_id === $actor->id;

        // submitted → approved/rejected: APPROVE
        if ($from === AccountabilityStatus::SUBMITTED
            && in_array($to, [AccountabilityStatus::APPROVED, AccountabilityStatus::REJECTED], true)) {
            if (! $isApprover && ! $isManager) {
                throw ValidationException::withMessages([
                    'accountability_status' => 'Você não tem permissão para aprovar/rejeitar prestações de contas.',
                ]);
            }

            return;
        }

        // in_progress/rejected → submitted: solicitante ou ACCOUNTABILITY
        if ($to === AccountabilityStatus::SUBMITTED) {
            if (! $isOwner && ! $canManageAccountability && ! $isManager) {
                throw ValidationException::withMessages([
                    'accountability_status' => 'Você não tem permissão para enviar prestação de contas.',
                ]);
            }

            return;
        }

        // submitted → in_progress (voltar pra correção): aprovador
        if ($from === AccountabilityStatus::SUBMITTED && $to === AccountabilityStatus::IN_PROGRESS) {
            if (! $isApprover && ! $isManager) {
                throw ValidationException::withMessages([
                    'accountability_status' => 'Você não tem permissão para devolver prestação para correção.',
                ]);
            }

            return;
        }

        // pending ↔ in_progress: automáticas (chamadas pelo AccountabilityService),
        // permite qualquer um com ACCOUNTABILITY
        if (! $canManageAccountability && ! $isManager && ! $isOwner) {
            throw ValidationException::withMessages([
                'accountability_status' => 'Você não tem permissão para alterar status da prestação.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function validateAccountabilityPreconditions(
        TravelExpense $te,
        AccountabilityStatus $target,
        ?string $note
    ): void {
        // Prestação só pode ser submetida se a verba estiver APROVADA
        if ($target === AccountabilityStatus::SUBMITTED
            && $te->status !== TravelExpenseStatus::APPROVED) {
            throw ValidationException::withMessages([
                'accountability_status' => 'Só é possível enviar prestação de contas após aprovação da verba.',
            ]);
        }

        // Submeter prestação exige pelo menos 1 item
        if ($target === AccountabilityStatus::SUBMITTED && $te->items()->count() === 0) {
            throw ValidationException::withMessages([
                'accountability_status' => 'Adicione ao menos um item antes de enviar a prestação.',
            ]);
        }

        if ($target === AccountabilityStatus::REJECTED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'Informe o motivo da rejeição da prestação.',
            ]);
        }
    }

    protected function fillAccountabilityRejection(array &$update, ?string $note): void
    {
        $update['accountability_rejected_at'] = now();
        $update['accountability_rejection_reason'] = $note;
    }
}
