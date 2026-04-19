<?php

namespace App\Services;

use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Models\ReturnOrder;
use App\Models\ReturnOrderFile;
use App\Models\ReturnOrderItem;
use App\Models\ReturnOrderStatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de solicitações de devolução (return_orders). Não manipula
 * status além da criação (estado inicial = pending) — transições
 * devem passar pelo ReturnOrderTransitionService.
 */
class ReturnOrderService
{
    public function __construct(
        protected ReturnOrderLookupService $lookup,
    ) {}

    /**
     * Cria uma solicitação de devolução vinculada à NF/cupom via lookup
     * em `movements`. Persiste snapshot da venda + itens selecionados
     * pelo atendente.
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor): ReturnOrder
    {
        $type = ReturnType::from($data['type']);
        $category = ReturnReasonCategory::from($data['reason_category']);

        // Resolve a NF em movements e usa como fonte de verdade do snapshot
        $lookupPayload = $this->lookup->lookupInvoice(
            $data['invoice_number'],
            $data['store_code_filter'] ?? null,
            $data['movement_date_filter'] ?? null
        );

        if (! $lookupPayload['found']) {
            throw ValidationException::withMessages([
                'invoice_number' => 'NF/cupom não encontrado nas movimentações do e-commerce. Verifique o número.',
            ]);
        }

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Selecione ao menos um item para devolução.',
            ]);
        }

        // Valida que todos os items selecionados pertencem à NF
        $validMovementIds = array_map(fn ($i) => (int) $i['movement_id'], $lookupPayload['items']);
        $selectedMovementIds = array_map(fn ($i) => (int) $i['movement_id'], $items);
        $invalid = array_diff($selectedMovementIds, $validMovementIds);
        if (! empty($invalid)) {
            throw ValidationException::withMessages([
                'items' => 'Alguns itens selecionados não pertencem a esta NF.',
            ]);
        }

        // Calcula amount_items (sempre — baseado nos items selecionados)
        $amountItems = $this->calculateAmountItems($items, $lookupPayload);

        // refund_amount: só relevante para estorno/credito; default = amount_items
        $refundAmount = null;
        if ($type->requiresRefundAmount()) {
            $refundAmount = isset($data['refund_amount']) && $data['refund_amount'] !== ''
                ? (float) $data['refund_amount']
                : $amountItems;

            if ($refundAmount <= 0) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Valor de reembolso deve ser maior que zero.',
                ]);
            }

            if ($refundAmount > $amountItems) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Valor de reembolso não pode ser maior que o total dos itens devolvidos.',
                ]);
            }
        }

        $this->ensureNoDuplicate(
            $lookupPayload['invoice_number'],
            $lookupPayload['store_code'],
            $type
        );

        return DB::transaction(function () use ($data, $type, $category, $lookupPayload, $items, $amountItems, $refundAmount, $actor) {
            $order = ReturnOrder::create([
                'invoice_number' => $lookupPayload['invoice_number'],
                'store_code' => $lookupPayload['store_code'],
                'movement_date' => $lookupPayload['movement_date'],
                'cpf_customer' => $lookupPayload['cpf_customer'],
                'customer_name' => $data['customer_name'],
                'cpf_consultant' => $lookupPayload['cpf_consultant'],
                'employee_id' => $data['employee_id'] ?? null,
                'sale_total' => $lookupPayload['sale_total'],
                'type' => $type->value,
                'amount_items' => $amountItems,
                'refund_amount' => $refundAmount,
                'status' => ReturnStatus::PENDING->value,
                'reason_category' => $category->value,
                'return_reason_id' => $data['return_reason_id'] ?? null,
                'reverse_tracking_code' => $data['reverse_tracking_code'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->persistItems($order, $items, $lookupPayload);

            ReturnOrderStatusHistory::create([
                'return_order_id' => $order->id,
                'from_status' => null,
                'to_status' => ReturnStatus::PENDING->value,
                'changed_by_user_id' => $actor->id,
                'note' => 'Devolução criada',
                'created_at' => now(),
            ]);

            return $order->fresh(['items', 'reason', 'statusHistory']);
        });
    }

    /**
     * Atualiza dados editáveis. Status muda via Transition Service.
     * Edição permitida em estados iniciais (pending ou approved).
     *
     * @throws ValidationException
     */
    public function update(ReturnOrder $order, array $data, User $actor): ReturnOrder
    {
        if ($order->is_deleted) {
            throw ValidationException::withMessages([
                'return' => 'Não é possível editar uma devolução excluída.',
            ]);
        }

        $editableStates = [
            ReturnStatus::PENDING,
            ReturnStatus::APPROVED,
        ];

        if (! in_array($order->status, $editableStates, true)) {
            throw ValidationException::withMessages([
                'status' => 'Devolução em estado avançado só pode ter observações ou anexos atualizados.',
            ]);
        }

        unset(
            $data['status'],
            $data['invoice_number'],
            $data['store_code'],
            $data['sale_total'],
            $data['movement_date'],
            $data['type'],
            $data['amount_items'],
            $data['created_by_user_id'],
            $data['approved_by_user_id'],
            $data['processed_by_user_id'],
            $data['deleted_at'],
            $data['deleted_by_user_id'],
            $data['approved_at'],
            $data['completed_at'],
            $data['cancelled_at'],
        );

        // Validação de refund_amount quando tipo exige
        if ($order->requiresRefundAmount() && isset($data['refund_amount'])) {
            $refund = (float) $data['refund_amount'];
            if ($refund <= 0) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Valor de reembolso deve ser maior que zero.',
                ]);
            }
            if ($refund > (float) $order->amount_items) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Valor de reembolso não pode ser maior que o total dos itens.',
                ]);
            }
        }

        $order->fill($data);
        $order->updated_by_user_id = $actor->id;
        $order->save();

        return $order->fresh(['items', 'reason', 'statusHistory', 'files']);
    }

    /**
     * Soft delete com motivo. Bloqueia se já foi concluído.
     *
     * @throws ValidationException
     */
    public function softDelete(ReturnOrder $order, User $actor, string $reason): ReturnOrder
    {
        if ($order->is_deleted) {
            throw ValidationException::withMessages([
                'return' => 'Devolução já foi excluída.',
            ]);
        }

        if ($order->status === ReturnStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'return' => 'Devoluções já concluídas não podem ser excluídas — mantém-se para auditoria.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'deleted_reason' => 'É obrigatório informar o motivo da exclusão.',
            ]);
        }

        $order->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->id,
            'deleted_reason' => $reason,
        ]);

        return $order->fresh();
    }

    /**
     * Bloqueia duplicata: mesma NF + loja + type (não permite duas
     * devoluções simultaneamente ativas com o mesmo tipo na mesma NF).
     * Alinhado com a restrição equivalente de Reversals.
     *
     * @throws ValidationException
     */
    public function ensureNoDuplicate(string $invoiceNumber, string $storeCode, ReturnType $type): void
    {
        $exists = ReturnOrder::query()
            ->where('invoice_number', $invoiceNumber)
            ->where('store_code', $storeCode)
            ->where('type', $type->value)
            ->whereNull('deleted_at')
            ->whereNotIn('status', [
                ReturnStatus::CANCELLED->value,
            ])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Já existe uma devolução ativa do tipo '.$type->label().' para esta NF/cupom. Cancele a anterior antes de criar outra.',
            ]);
        }
    }

    /**
     * Anexa arquivos (foto do produto, comprovante postagem, print
     * da conversa, etc.).
     */
    public function attachFiles(ReturnOrder $order, array $files, User $actor): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store("return-orders/{$order->id}", 'public');

            ReturnOrderFile::create([
                'return_order_id' => $order->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by_user_id' => $actor->id,
            ]);
        }
    }

    public function deleteFile(ReturnOrderFile $file): void
    {
        Storage::disk('public')->delete($file->file_path);
        $file->delete();
    }

    /**
     * Calcula o total dos itens selecionados a partir dos realized_value
     * dos movements correspondentes.
     */
    protected function calculateAmountItems(array $items, array $lookup): float
    {
        $selectedIds = array_map(fn ($i) => (int) $i['movement_id'], $items);

        return (float) collect($lookup['items'])
            ->whereIn('movement_id', $selectedIds)
            ->sum('realized_value');
    }

    /**
     * Persiste os items com snapshot dos dados do produto do movements.
     */
    protected function persistItems(ReturnOrder $order, array $items, array $lookup): void
    {
        $byMovementId = collect($lookup['items'])->keyBy('movement_id');

        foreach ($items as $item) {
            $movementId = (int) $item['movement_id'];
            $source = $byMovementId[$movementId] ?? null;

            if (! $source) {
                continue;
            }

            // Cliente pode devolver quantidade menor que a comprada (ex: 2 de 3 unidades)
            $requestedQty = isset($item['quantity']) && $item['quantity'] !== ''
                ? (float) $item['quantity']
                : (float) $source['quantity'];

            $unitPrice = (float) $source['unit_price'];
            $subtotal = $unitPrice * $requestedQty;

            ReturnOrderItem::create([
                'return_order_id' => $order->id,
                'movement_id' => $movementId,
                'reference' => $source['reference'],
                'size' => $source['size'],
                'barcode' => $source['barcode'],
                'product_name' => $item['product_name'] ?? null,
                'quantity' => $requestedQty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);
        }

        // Recalcula amount_items com base nos items persistidos (casos onde
        // o cliente devolveu quantidade parcial).
        $actualAmount = (float) $order->items()->sum('subtotal');
        $order->update(['amount_items' => $actualAmount]);
    }
}
