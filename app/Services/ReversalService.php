<?php

namespace App\Services;

use App\Enums\ReversalPartialMode;
use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Models\Movement;
use App\Models\Reversal;
use App\Models\ReversalFile;
use App\Models\ReversalItem;
use App\Models\ReversalStatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de estornos. Não manipula status além da criação (estado inicial
 * = pending_reversal) — transições devem passar pelo
 * ReversalTransitionService.
 */
class ReversalService
{
    public function __construct(
        protected ReversalLookupService $lookup,
    ) {}

    /**
     * Cria um estorno vinculando à NF/cupom via lookup em `movements`.
     * Calcula amount_reversal conforme type/partial_mode e grava linhas
     * em reversal_items quando type=partial AND partial_mode=by_item.
     *
     * @param array $data Dados do formulário (já validados).
     * @throws ValidationException
     */
    public function create(array $data, User $actor): Reversal
    {
        $type = ReversalType::from($data['type']);
        $partialMode = isset($data['partial_mode'])
            ? ReversalPartialMode::from($data['partial_mode'])
            : null;

        // Resolve a NF em movements e usa o payload como fonte de verdade
        // do snapshot (store_code, movement_date, cpf_consultant, sale_total).
        $lookup = $this->lookup->lookupInvoice(
            $data['invoice_number'],
            $data['store_code_filter'] ?? null,
            $data['movement_date_filter'] ?? null
        );

        if (! $lookup['found']) {
            throw ValidationException::withMessages([
                'invoice_number' => 'NF/cupom não encontrado nas movimentações. Verifique o número e a loja.',
            ]);
        }

        $amounts = $this->calculateAmounts($type, $partialMode, $data, $lookup);

        $this->ensureNoDuplicate(
            $lookup['invoice_number'],
            $lookup['store_code'],
            $amounts['amount_original']
        );

        return DB::transaction(function () use ($data, $type, $partialMode, $lookup, $amounts, $actor) {
            $reversal = Reversal::create([
                'invoice_number' => $lookup['invoice_number'],
                'store_code' => $lookup['store_code'],
                'movement_date' => $lookup['movement_date'],
                'cpf_customer' => $lookup['cpf_customer'],
                'customer_name' => $data['customer_name'],
                'cpf_consultant' => $lookup['cpf_consultant'],
                'employee_id' => $data['employee_id'] ?? null,
                'sale_total' => $lookup['sale_total'],
                'type' => $type->value,
                'partial_mode' => $partialMode?->value,
                'amount_original' => $amounts['amount_original'],
                'amount_correct' => $amounts['amount_correct'],
                'amount_reversal' => $amounts['amount_reversal'],
                'status' => ReversalStatus::PENDING_REVERSAL->value,
                'reversal_reason_id' => $data['reversal_reason_id'],
                'expected_refund_date' => $data['expected_refund_date'] ?? null,
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'payment_brand' => $data['payment_brand'] ?? null,
                'installments_count' => $data['installments_count'] ?? null,
                'nsu' => $data['nsu'] ?? null,
                'authorization_code' => $data['authorization_code'] ?? null,
                'pix_key_type' => $data['pix_key_type'] ?? null,
                'pix_key' => $data['pix_key'] ?? null,
                'pix_beneficiary' => $data['pix_beneficiary'] ?? null,
                'pix_bank_id' => $data['pix_bank_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            if ($partialMode === ReversalPartialMode::BY_ITEM) {
                $this->persistItems($reversal, $data['items'] ?? [], $lookup);
            }

            ReversalStatusHistory::create([
                'reversal_id' => $reversal->id,
                'from_status' => null,
                'to_status' => ReversalStatus::PENDING_REVERSAL->value,
                'changed_by_user_id' => $actor->id,
                'note' => 'Estorno criado',
                'created_at' => now(),
            ]);

            return $reversal->fresh(['items', 'reason', 'statusHistory', 'paymentType']);
        });
    }

    /**
     * Atualiza dados editáveis do estorno. Status só muda via
     * ReversalTransitionService. Edição permitida enquanto estiver em
     * estados iniciais (pending_reversal ou pending_authorization).
     *
     * @throws ValidationException
     */
    public function update(Reversal $reversal, array $data, User $actor): Reversal
    {
        if ($reversal->is_deleted) {
            throw ValidationException::withMessages([
                'reversal' => 'Não é possível editar um estorno excluído.',
            ]);
        }

        $editableStates = [
            ReversalStatus::PENDING_REVERSAL,
            ReversalStatus::PENDING_AUTHORIZATION,
        ];

        if (! in_array($reversal->status, $editableStates, true)) {
            throw ValidationException::withMessages([
                'status' => 'Estorno em estado avançado só pode ter observações ou anexos atualizados.',
            ]);
        }

        unset(
            $data['status'],
            $data['invoice_number'],
            $data['store_code'],
            $data['sale_total'],
            $data['movement_date'],
            $data['type'],
            $data['partial_mode'],
            $data['created_by_user_id'],
            $data['authorized_by_user_id'],
            $data['processed_by_user_id'],
            $data['deleted_at'],
            $data['deleted_by_user_id'],
            $data['reversed_at'],
            $data['cancelled_at'],
            $data['synced_to_cigam_at'],
            $data['helpdesk_ticket_id'],
        );

        $reversal->fill($data);
        $reversal->updated_by_user_id = $actor->id;
        $reversal->save();

        return $reversal->fresh(['items', 'reason', 'statusHistory', 'paymentType', 'files']);
    }

    /**
     * Soft delete com motivo obrigatório. Bloqueia se já foi estornado
     * (status=reversed) — estornos executados devem permanecer auditáveis.
     *
     * @throws ValidationException
     */
    public function softDelete(Reversal $reversal, User $actor, string $reason): Reversal
    {
        if ($reversal->is_deleted) {
            throw ValidationException::withMessages([
                'reversal' => 'Estorno já foi excluído.',
            ]);
        }

        if ($reversal->status === ReversalStatus::REVERSED) {
            throw ValidationException::withMessages([
                'reversal' => 'Estornos já executados não podem ser excluídos — mantém-se para auditoria.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'deleted_reason' => 'É obrigatório informar o motivo da exclusão.',
            ]);
        }

        $reversal->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->id,
            'deleted_reason' => $reason,
        ]);

        return $reversal->fresh();
    }

    /**
     * Bloqueia criação de duplicata exata enquanto não for soft-deleted.
     * Substitui a janela de 5 minutos da v1 por uma checagem explícita
     * no service — MySQL não suporta partial unique indexes.
     *
     * @throws ValidationException
     */
    public function ensureNoDuplicate(string $invoiceNumber, string $storeCode, float $amountOriginal): void
    {
        $exists = Reversal::query()
            ->where('invoice_number', $invoiceNumber)
            ->where('store_code', $storeCode)
            ->where('amount_original', $amountOriginal)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Já existe um estorno ativo para esta NF/cupom com o mesmo valor. Cancele o anterior antes de criar outro.',
            ]);
        }
    }

    /**
     * Anexa arquivos a um estorno. Aceita múltiplos uploads.
     */
    public function attachFiles(Reversal $reversal, array $files, User $actor): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store("reversals/{$reversal->id}", 'public');

            ReversalFile::create([
                'reversal_id' => $reversal->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by_user_id' => $actor->id,
            ]);
        }
    }

    /**
     * Remove um anexo (arquivo do storage + linha do banco).
     */
    public function deleteFile(ReversalFile $file): void
    {
        Storage::disk('public')->delete($file->file_path);
        $file->delete();
    }

    /**
     * Calcula amount_original, amount_correct e amount_reversal conforme
     * type e partial_mode.
     *
     *  - total: amount_original = amount_reversal = sale_total
     *  - partial by_value: original = sale_total, correct informado,
     *    reversal = max(0, original - correct)
     *  - partial by_item: original = sale_total, reversal = soma dos
     *    itens selecionados (calculada a partir de data['items'])
     *
     * @throws ValidationException
     */
    protected function calculateAmounts(
        ReversalType $type,
        ?ReversalPartialMode $partialMode,
        array $data,
        array $lookup
    ): array {
        $saleTotal = (float) $lookup['sale_total'];

        if ($type === ReversalType::TOTAL) {
            return [
                'amount_original' => $saleTotal,
                'amount_correct' => null,
                'amount_reversal' => $saleTotal,
            ];
        }

        // Partial
        if ($partialMode === ReversalPartialMode::BY_VALUE) {
            $correct = (float) ($data['amount_correct'] ?? 0);
            $reversal = max(0.0, $saleTotal - $correct);

            if ($reversal <= 0) {
                throw ValidationException::withMessages([
                    'amount_correct' => 'Valor correto deve ser menor que o total da NF.',
                ]);
            }

            return [
                'amount_original' => $saleTotal,
                'amount_correct' => $correct,
                'amount_reversal' => $reversal,
            ];
        }

        if ($partialMode === ReversalPartialMode::BY_ITEM) {
            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => 'Selecione ao menos um item para estorno parcial por produto.',
                ]);
            }

            $selectedIds = array_map(fn ($i) => (int) $i['movement_id'], $items);
            $validIds = array_map(fn ($i) => (int) $i['movement_id'], $lookup['items']);

            $invalid = array_diff($selectedIds, $validIds);
            if (! empty($invalid)) {
                throw ValidationException::withMessages([
                    'items' => 'Alguns itens selecionados não pertencem a esta NF.',
                ]);
            }

            $selectedMovements = collect($lookup['items'])
                ->whereIn('movement_id', $selectedIds);

            $reversal = (float) $selectedMovements->sum('realized_value');

            if ($reversal <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Soma dos itens selecionados não pode ser zero.',
                ]);
            }

            return [
                'amount_original' => $saleTotal,
                'amount_correct' => null,
                'amount_reversal' => $reversal,
            ];
        }

        throw ValidationException::withMessages([
            'partial_mode' => 'Modo de estorno parcial não reconhecido.',
        ]);
    }

    /**
     * Cria linhas em reversal_items quando partial_mode=by_item.
     * Snapshot dos dados do produto para estabilidade histórica.
     */
    protected function persistItems(Reversal $reversal, array $items, array $lookup): void
    {
        $movementById = collect($lookup['items'])->keyBy('movement_id');

        foreach ($items as $item) {
            $movementId = (int) $item['movement_id'];
            $source = $movementById[$movementId] ?? null;

            if (! $source) {
                continue;
            }

            $quantity = (float) ($item['quantity'] ?? $source['quantity']);
            $unitPrice = (float) $source['unit_price'];
            $amount = (float) $source['realized_value'];

            ReversalItem::create([
                'reversal_id' => $reversal->id,
                'movement_id' => $movementId,
                'barcode' => $source['barcode'],
                'ref_size' => $source['ref_size'],
                'product_name' => $item['product_name'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ]);
        }
    }
}
