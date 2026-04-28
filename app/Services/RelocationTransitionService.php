<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\RelocationItemReason;
use App\Enums\RelocationStatus;
use App\Events\RelocationStatusChanged;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationStatusHistory;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de remanejos. Ponto único de mutação de Relocation::status.
 * Outros serviços e controllers NUNCA devem setar o campo direto.
 *
 * Transições válidas (RelocationStatus::allowedTransitions):
 *   draft → requested | cancelled
 *   requested → approved | rejected | cancelled
 *   approved → in_separation | cancelled
 *   in_separation → in_transit | cancelled
 *   in_transit → completed | partial
 *   completed | partial | rejected | cancelled → [] (terminais)
 *
 * Permissões por transição:
 *  - draft → requested: CREATE_RELOCATIONS ou EDIT_RELOCATIONS
 *  - requested → approved | rejected: APPROVE_RELOCATIONS
 *  - approved → in_separation: SEPARATE_RELOCATIONS (loja origem inicia separação)
 *  - in_separation → in_transit: SEPARATE_RELOCATIONS + invoice_number obrigatório
 *  - in_transit → completed | partial: RECEIVE_RELOCATIONS
 *  - * → cancelled: APPROVE_RELOCATIONS + note (motivo). Bloqueado a partir
 *    de in_transit (Transfer já criado, exige fluxo de devolução).
 *
 * Side effects automáticos:
 *  - approved: grava approved_at + approved_by_user_id
 *  - in_separation: grava separated_at + separated_by_user_id (separação iniciada)
 *  - in_transit: cria Transfer (transfer_type='relocation'), linka via
 *    transfer_id, grava in_transit_at + invoice_number/invoice_date
 *  - completed/partial: grava completed_at + received_by_user_id, agrega
 *    qty_received nos itens (payload `received_items`)
 *  - rejected: grava rejected_at + rejected_reason (note)
 *  - cancelled: grava cancelled_at + cancelled_reason (note)
 */
class RelocationTransitionService
{
    public function __construct(
        protected RelocationStockValidator $stockValidator,
    ) {}

    /**
     * Executa uma transição de status.
     *
     * Payload extras esperados em transições específicas:
     *  - in_separation → in_transit: ['invoice_number', 'invoice_date'?]
     *  - in_transit → completed/partial: ['received_items' => [
     *        ['id' => $itemId, 'qty_received' => N, 'reason_code'? => '...']
     *    ]]
     *
     * @throws ValidationException
     */
    public function transition(
        Relocation $relocation,
        RelocationStatus|string $toStatus,
        User $actor,
        ?string $note = null,
        array $payload = []
    ): Relocation {
        if ($relocation->is_deleted) {
            throw ValidationException::withMessages([
                'relocation' => 'Não é possível transicionar um remanejo excluído.',
            ]);
        }

        $target = $toStatus instanceof RelocationStatus
            ? $toStatus
            : RelocationStatus::from($toStatus);
        $current = $relocation->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        $this->authorizeTransition($current, $target, $actor);
        $this->validateTransitionPayload($current, $target, $relocation, $payload, $note);

        return DB::transaction(function () use ($relocation, $current, $target, $actor, $note, $payload) {
            $update = [
                'status' => $target->value,
                'updated_by_user_id' => $actor->id,
            ];

            $this->applyStatusSideEffects($update, $current, $target, $actor, $note, $payload, $relocation);

            $relocation->update($update);

            // Side effects que tocam outras tabelas (items, transfers)
            if ($target === RelocationStatus::IN_TRANSIT) {
                $this->createTransferOnDispatch($relocation->fresh(), $payload, $actor);
            }

            if (in_array($target, [RelocationStatus::COMPLETED, RelocationStatus::PARTIAL], true)) {
                $this->applyReceivedItems($relocation, $payload['received_items'] ?? []);
                $this->confirmTransferOnReceive($relocation->fresh(), $payload, $actor);
            }

            RelocationStatusHistory::create([
                'relocation_id' => $relocation->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            $fresh = $relocation->fresh([
                'items',
                'type',
                'originStore',
                'destinationStore',
                'transfer',
                'statusHistory',
            ]);

            // Eventos auto-discovered (Laravel 12) — listeners registrados via
            // typed handle(RelocationStatusChanged $e). NÃO chamar Event::listen
            // manualmente, gera duplicação.
            RelocationStatusChanged::dispatch($fresh, $current, $target, $actor, $note);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------
    // Autorização e validação
    // ------------------------------------------------------------------

    /**
     * @throws ValidationException
     */
    protected function authorizeTransition(
        RelocationStatus $from,
        RelocationStatus $to,
        User $actor
    ): void {
        // Cancelamento — APPROVE_RELOCATIONS sempre exigido
        if ($to === RelocationStatus::CANCELLED) {
            $this->requirePermission($actor, Permission::APPROVE_RELOCATIONS, 'cancelar remanejos');

            return;
        }

        // Solicitar (draft → requested) — autor ou editor pode submeter
        if ($from === RelocationStatus::DRAFT && $to === RelocationStatus::REQUESTED) {
            $allowed = $actor->hasPermissionTo(Permission::CREATE_RELOCATIONS->value)
                || $actor->hasPermissionTo(Permission::EDIT_RELOCATIONS->value);

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para solicitar remanejos.',
                ]);
            }

            return;
        }

        // Aprovação ou rejeição (requested → approved | rejected) — APPROVE
        if ($from === RelocationStatus::REQUESTED
            && in_array($to, [RelocationStatus::APPROVED, RelocationStatus::REJECTED], true)
        ) {
            $this->requirePermission($actor, Permission::APPROVE_RELOCATIONS, 'aprovar/rejeitar remanejos');

            return;
        }

        // Iniciar separação (approved → in_separation) — SEPARATE
        if ($from === RelocationStatus::APPROVED && $to === RelocationStatus::IN_SEPARATION) {
            $this->requirePermission($actor, Permission::SEPARATE_RELOCATIONS, 'iniciar separação');

            return;
        }

        // Despacho com NF (in_separation → in_transit) — SEPARATE
        if ($from === RelocationStatus::IN_SEPARATION && $to === RelocationStatus::IN_TRANSIT) {
            $this->requirePermission($actor, Permission::SEPARATE_RELOCATIONS, 'confirmar envio (NF)');

            return;
        }

        // Recebimento (in_transit → completed | partial) — RECEIVE
        if ($from === RelocationStatus::IN_TRANSIT
            && in_array($to, [RelocationStatus::COMPLETED, RelocationStatus::PARTIAL], true)
        ) {
            $this->requirePermission($actor, Permission::RECEIVE_RELOCATIONS, 'confirmar recebimento');

            return;
        }
    }

    /**
     * @throws ValidationException
     */
    protected function requirePermission(User $actor, Permission $perm, string $action): void
    {
        if (! $actor->hasPermissionTo($perm->value)) {
            throw ValidationException::withMessages([
                'status' => "Você não tem permissão para {$action}.",
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function validateTransitionPayload(
        RelocationStatus $from,
        RelocationStatus $to,
        Relocation $relocation,
        array $payload,
        ?string $note
    ): void {
        // Cancelamento e rejeição exigem motivo
        if (in_array($to, [RelocationStatus::CANCELLED, RelocationStatus::REJECTED], true)) {
            if (! $note || trim($note) === '') {
                throw ValidationException::withMessages([
                    'note' => 'É obrigatório informar o motivo.',
                ]);
            }
        }

        // Cancelamento bloqueado a partir de in_transit
        if ($to === RelocationStatus::CANCELLED && ! $relocation->status->isPreTransit()) {
            throw ValidationException::withMessages([
                'status' => 'Remanejos em trânsito ou recebidos não podem ser cancelados — já existe transferência física vinculada.',
            ]);
        }

        // Despacho exige NF
        if ($from === RelocationStatus::IN_SEPARATION && $to === RelocationStatus::IN_TRANSIT) {
            $invoice = trim((string) ($payload['invoice_number'] ?? $relocation->invoice_number ?? ''));
            if ($invoice === '') {
                throw ValidationException::withMessages([
                    'invoice_number' => 'Número da NF de transferência é obrigatório para confirmar envio.',
                ]);
            }

            // Pelo menos um item separado é exigido
            $hasSeparated = $relocation->items()->where('qty_separated', '>', 0)->exists();
            if (! $hasSeparated) {
                throw ValidationException::withMessages([
                    'items' => 'Informe a quantidade separada em pelo menos um item antes de confirmar envio.',
                ]);
            }
        }

        // Recebimento exige received_items para casar com itens existentes
        if (in_array($to, [RelocationStatus::COMPLETED, RelocationStatus::PARTIAL], true)) {
            $items = $payload['received_items'] ?? [];
            if (empty($items)) {
                throw ValidationException::withMessages([
                    'received_items' => 'Informe a quantidade recebida de pelo menos um item.',
                ]);
            }
        }

        // Aprovação valida saldo absoluto na origem — saldo pode ter mudado
        // entre criação e aprovação (vendas paralelas, outros remanejos
        // aprovados etc.). Override via force_approve_without_stock=true
        // no payload pra casos onde o planejamento já avaliou a situação.
        if ($from === RelocationStatus::REQUESTED && $to === RelocationStatus::APPROVED) {
            $force = (bool) ($payload['force_approve_without_stock'] ?? false);
            if (! $force) {
                $items = $relocation->items->map(fn ($it) => [
                    'barcode' => $it->barcode,
                    'qty_requested' => (int) $it->qty_requested,
                ])->all();

                $this->stockValidator->validate(
                    $relocation->origin_store_id,
                    $items,
                    $relocation->id, // exclui o próprio remanejo do committed
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Side effects de transição
    // ------------------------------------------------------------------

    /**
     * Aplica updates sobre o array $update conforme o destino. Mutate-style
     * para minimizar overhead — atualização do model acontece no caller.
     *
     * @param array<string, mixed> $update Por referência
     * @param array<string, mixed> $payload
     */
    protected function applyStatusSideEffects(
        array &$update,
        RelocationStatus $from,
        RelocationStatus $to,
        User $actor,
        ?string $note,
        array $payload,
        Relocation $relocation
    ): void {
        $now = now();

        switch ($to) {
            case RelocationStatus::REQUESTED:
                $update['requested_at'] = $now;
                break;

            case RelocationStatus::APPROVED:
                $update['approved_at'] = $now;
                $update['approved_by_user_id'] = $actor->id;
                break;

            case RelocationStatus::IN_SEPARATION:
                $update['separated_at'] = $now;
                $update['separated_by_user_id'] = $actor->id;
                break;

            case RelocationStatus::IN_TRANSIT:
                $update['in_transit_at'] = $now;
                $update['invoice_number'] = $payload['invoice_number'] ?? $relocation->invoice_number;
                $update['invoice_date'] = $payload['invoice_date'] ?? $now->toDateString();
                // Snapshot da validação de NF — frontend envia o resultado
                // do preview (RelocationDispatchValidationService) junto com
                // a confirmação. Persistido pra timeline e auditoria; quando
                // tem discrepâncias, listener dispara alerta pra logística.
                if (array_key_exists('dispatch_validation', $payload) && is_array($payload['dispatch_validation'])) {
                    $v = $payload['dispatch_validation'];
                    $update['dispatch_has_discrepancies'] = (bool) ($v['has_discrepancies'] ?? false);
                    $update['dispatch_discrepancies_json'] = $v;
                    $update['dispatch_validated_at'] = $now;
                }
                break;

            case RelocationStatus::COMPLETED:
            case RelocationStatus::PARTIAL:
                $update['completed_at'] = $now;
                $update['received_by_user_id'] = $actor->id;
                break;

            case RelocationStatus::REJECTED:
                $update['rejected_at'] = $now;
                $update['rejected_reason'] = $note;
                break;

            case RelocationStatus::CANCELLED:
                $update['cancelled_at'] = $now;
                $update['cancelled_reason'] = $note;
                break;

            default:
                break;
        }
    }

    /**
     * Cria o Transfer físico vinculado quando o remanejo é despachado.
     * O Transfer herda origin/destination/invoice e nasce já em status
     * `in_transit` (a separação física já foi feita aqui).
     */
    protected function createTransferOnDispatch(Relocation $relocation, array $payload, User $actor): Transfer
    {
        // Idempotência — se já existe Transfer linkado (transição re-disparada
        // por algum motivo), retorna o existente sem duplicar.
        if ($relocation->transfer_id) {
            $existing = Transfer::find($relocation->transfer_id);
            if ($existing) {
                return $existing;
            }
        }

        $totalProducts = (int) $relocation->items()->sum('qty_separated');

        $transfer = Transfer::create([
            'relocation_id' => $relocation->id,
            'origin_store_id' => $relocation->origin_store_id,
            'destination_store_id' => $relocation->destination_store_id,
            'invoice_number' => $relocation->invoice_number,
            'volumes_qty' => $payload['volumes_qty'] ?? null,
            'products_qty' => $totalProducts,
            'transfer_type' => 'relocation',
            'status' => 'in_transit',
            'observations' => $relocation->observations,
            'created_by_user_id' => $actor->id,
            'pickup_date' => now()->toDateString(),
            'pickup_time' => now()->toTimeString(),
        ]);

        $relocation->update(['transfer_id' => $transfer->id]);

        return $transfer;
    }

    /**
     * Aplica qty_received e reason_code aos itens conforme payload do
     * recebimento. Itens não citados ficam com qty_received=0 (nada recebido).
     *
     * `reason_code` deve estar no enum RelocationItemReason — valores
     * arbitrários são rejeitados.
     *
     * @param array<int, array{id: int, qty_received: int, reason_code?: string|null, observations?: string|null}> $items
     */
    protected function applyReceivedItems(Relocation $relocation, array $items): void
    {
        $byId = [];
        $validReasons = RelocationItemReason::values();

        foreach ($items as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $byId[$id] = $row;

            // Valida reason_code contra o enum
            $reason = $row['reason_code'] ?? null;
            if ($reason !== null && $reason !== '' && ! in_array($reason, $validReasons, true)) {
                throw ValidationException::withMessages([
                    'received_items' => "Motivo '{$reason}' inválido. Use um dos: ".implode(', ', $validReasons).'.',
                ]);
            }
        }

        if (empty($byId)) {
            return;
        }

        $existing = $relocation->items()->whereIn('id', array_keys($byId))->get();

        foreach ($existing as $item) {
            /** @var RelocationItem $item */
            $row = $byId[$item->id];

            $qtyReceived = max(0, (int) ($row['qty_received'] ?? 0));
            // Não pode exceder o que foi separado
            if ($item->qty_separated > 0 && $qtyReceived > $item->qty_separated) {
                throw ValidationException::withMessages([
                    'received_items' => "Quantidade recebida do item {$item->product_reference} excede a separada.",
                ]);
            }

            $item->qty_received = $qtyReceived;
            $reasonValue = $row['reason_code'] ?? null;
            $item->reason_code = $reasonValue !== '' ? $reasonValue : null;
            if (array_key_exists('observations', $row)) {
                $item->observations = $row['observations'];
            }
            $item->save();
        }
    }

    /**
     * Sincroniza o Transfer físico vinculado quando o remanejo é recebido
     * pela loja destino. Transfer.status vai pra `confirmed` (substituindo
     * o estado intermediário `delivered` — para Remanejos, recebimento e
     * confirmação acontecem no mesmo ato).
     *
     * Idempotente: se Transfer já está em `confirmed` ou `cancelled`,
     * apenas atualiza receiver_name (caso tenha sido informado tardiamente).
     */
    protected function confirmTransferOnReceive(Relocation $relocation, array $payload, $actor): void
    {
        if (! $relocation->transfer_id) {
            return;
        }

        $transfer = Transfer::find($relocation->transfer_id);
        if (! $transfer) {
            return;
        }

        $update = [];
        if (! empty($payload['receiver_name'])) {
            $update['receiver_name'] = $payload['receiver_name'];
        }

        // Só transita pra confirmed se não estiver em estado terminal
        if (! in_array($transfer->status, ['confirmed', 'cancelled'], true)) {
            $update['status'] = 'confirmed';
            $update['confirmed_at'] = now();
            $update['confirmed_by_user_id'] = $actor->id;
            $update['delivery_date'] = $transfer->delivery_date ?? now()->toDateString();
            $update['delivery_time'] = $transfer->delivery_time ?? now()->toTimeString();
        }

        if (! empty($update)) {
            $transfer->update($update);
        }
    }
}
