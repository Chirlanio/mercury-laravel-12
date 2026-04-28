<?php

namespace App\Services;

use App\Enums\RelocationPriority;
use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationStatusHistory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de remanejos. NÃO manipula status além do estado inicial (draft) —
 * transições devem passar pelo RelocationTransitionService.
 *
 * Itens podem ser criados junto (`items` no payload) ou adicionados
 * separadamente via addItem/updateItem/removeItem (controller chamará
 * conforme o fluxo do form).
 */
class RelocationService
{
    public function __construct(
        protected CigamStockService $cigam,
        protected RelocationCommittedStockService $committed,
    ) {}

    /**
     * Cria um remanejo em estado draft com itens iniciais opcionais.
     *
     * Payload esperado:
     *  - relocation_type_id: int
     *  - origin_store_id: int
     *  - destination_store_id: int (≠ origem)
     *  - title?: string
     *  - observations?: string
     *  - priority?: 'low'|'normal'|'high'|'urgent' (default normal)
     *  - deadline_days?: int
     *  - items?: array<int, array{product_id?, product_reference, product_name?,
     *      product_color?, size?, barcode?, qty_requested}>
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor): Relocation
    {
        $this->validateStores($data['origin_store_id'] ?? null, $data['destination_store_id'] ?? null);

        $this->validateStockAvailability(
            (int) $data['origin_store_id'],
            $data['items'] ?? [],
        );

        $priority = isset($data['priority'])
            ? RelocationPriority::from($data['priority'])
            : RelocationPriority::NORMAL;

        // deadline_days é DERIVADO da prioridade (regra de negócio fixa).
        // Ignora qualquer valor manual no payload.
        $deadlineDays = $priority->deadlineDays();

        return DB::transaction(function () use ($data, $priority, $deadlineDays, $actor) {
            $relocation = Relocation::create([
                'ulid' => (string) Str::ulid(),
                'relocation_type_id' => $data['relocation_type_id'],
                'origin_store_id' => $data['origin_store_id'],
                'destination_store_id' => $data['destination_store_id'],
                'title' => $data['title'] ?? null,
                'observations' => $data['observations'] ?? null,
                'priority' => $priority->value,
                'deadline_days' => $deadlineDays,
                'status' => RelocationStatus::DRAFT->value,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->persistItems($relocation, $data['items']);
            }

            RelocationStatusHistory::create([
                'relocation_id' => $relocation->id,
                'from_status' => null,
                'to_status' => RelocationStatus::DRAFT->value,
                'changed_by_user_id' => $actor->id,
                'note' => 'Remanejo criado',
                'created_at' => now(),
            ]);

            return $relocation->fresh(['items', 'type', 'originStore', 'destinationStore', 'statusHistory']);
        });
    }

    /**
     * Clona um remanejo cancelado/rejeitado em novo DRAFT — útil pra
     * reabrir solicitação que foi rejeitada por motivo já corrigido,
     * ou pra refazer cancelamento acidental sem digitar tudo de novo.
     *
     * Copia: tipo, lojas, prioridade, título (com sufixo "(reaberto)"),
     * observações, items (qty_requested e barcode/ref/size — qty_separated
     * e qty_received começam zeradas).
     *
     * NÃO copia: status (sempre DRAFT), timestamps, transfer_id, NF,
     * snapshot de divergência, helpdesk ticket, deleted_*, audit user ids.
     *
     * Aplica a validação de saldo comprometido (mesmo create) — pode
     * falhar se origem não tem mais saldo do que tinha quando
     * cancelou/rejeitou.
     *
     * @throws ValidationException
     */
    public function cloneFrom(Relocation $original, User $actor): Relocation
    {
        if (! in_array($original->status, [RelocationStatus::CANCELLED, RelocationStatus::REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Apenas remanejos cancelados ou rejeitados podem ser reabertos.',
            ]);
        }

        $original->loadMissing('items');

        $items = $original->items->map(fn ($it) => [
            'product_id' => $it->product_id,
            'product_reference' => $it->product_reference,
            'product_name' => $it->product_name,
            'product_color' => $it->product_color,
            'size' => $it->size,
            'barcode' => $it->barcode,
            'qty_requested' => $it->qty_requested,
            'observations' => $it->observations,
        ])->all();

        $title = $original->title
            ? "{$original->title} (reaberto)"
            : "Reabertura do remanejo #{$original->id}";

        return $this->create([
            'relocation_type_id' => $original->relocation_type_id,
            'origin_store_id' => $original->origin_store_id,
            'destination_store_id' => $original->destination_store_id,
            'title' => $title,
            'observations' => $original->observations
                ? "{$original->observations}\n\n[Reaberto a partir do remanejo #{$original->id} ({$original->status->label()}).]"
                : "Reaberto a partir do remanejo #{$original->id} ({$original->status->label()}).",
            'priority' => $original->priority->value,
            'items' => $items,
        ], $actor);
    }

    /**
     * Atualiza dados editáveis. Status só muda via TransitionService.
     * Edição completa permitida em draft/requested; após approved só
     * campos não-críticos (observations, deadline_days, priority).
     *
     * @throws ValidationException
     */
    public function update(Relocation $relocation, array $data, User $actor): Relocation
    {
        if ($relocation->is_deleted) {
            throw ValidationException::withMessages([
                'relocation' => 'Não é possível editar um remanejo excluído.',
            ]);
        }

        $editableInAnyState = ['observations', 'priority', 'deadline_days'];

        $isFreeEdit = in_array($relocation->status, [
            RelocationStatus::DRAFT,
            RelocationStatus::REQUESTED,
        ], true);

        if (! $isFreeEdit) {
            // Filtra apenas campos não-críticos
            $data = array_intersect_key($data, array_flip($editableInAnyState));
        }

        if ($relocation->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Remanejo em estado terminal não pode ser editado.',
            ]);
        }

        // Bloqueia escrita direta em campos sensíveis mesmo em estados editáveis
        unset(
            $data['status'],
            $data['ulid'],
            $data['transfer_id'],
            $data['cigam_dispatched_at'],
            $data['cigam_received_at'],
            $data['helpdesk_ticket_id'],
            $data['created_by_user_id'],
            $data['approved_by_user_id'],
            $data['separated_by_user_id'],
            $data['received_by_user_id'],
            $data['requested_at'],
            $data['approved_at'],
            $data['separated_at'],
            $data['in_transit_at'],
            $data['completed_at'],
            $data['rejected_at'],
            $data['cancelled_at'],
            $data['deleted_at'],
            $data['deleted_by_user_id'],
        );

        if (isset($data['origin_store_id']) || isset($data['destination_store_id'])) {
            $this->validateStores(
                $data['origin_store_id'] ?? $relocation->origin_store_id,
                $data['destination_store_id'] ?? $relocation->destination_store_id
            );
        }

        // Se priority mudou, deadline_days é re-derivado automaticamente
        if (isset($data['priority'])) {
            $newPriority = RelocationPriority::from($data['priority']);
            $data['deadline_days'] = $newPriority->deadlineDays();
        } else {
            // Em update sem priority no payload, ignora qualquer deadline_days
            // manual — só priority muda o prazo.
            unset($data['deadline_days']);
        }

        $relocation->fill($data);
        $relocation->updated_by_user_id = $actor->id;
        $relocation->save();

        return $relocation->fresh(['items', 'type', 'originStore', 'destinationStore', 'statusHistory']);
    }

    /**
     * Soft delete. Bloqueado a partir de in_transit (já tem Transfer).
     *
     * @throws ValidationException
     */
    public function softDelete(Relocation $relocation, User $actor, string $reason): Relocation
    {
        if ($relocation->is_deleted) {
            throw ValidationException::withMessages([
                'relocation' => 'Remanejo já foi excluído.',
            ]);
        }

        if (! $relocation->isPreTransit() && $relocation->status !== RelocationStatus::REJECTED && $relocation->status !== RelocationStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'relocation' => 'Remanejos a partir de "Em Trânsito" não podem ser excluídos — já têm transferência física vinculada. Cancele a transferência se necessário.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'deleted_reason' => 'É obrigatório informar o motivo da exclusão.',
            ]);
        }

        $relocation->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->id,
            'deleted_reason' => $reason,
        ]);

        return $relocation->fresh();
    }

    /**
     * Adiciona um item ao remanejo. Permitido em estados editáveis
     * (draft, requested) ou pelo planejamento via MANAGE_RELOCATIONS.
     */
    public function addItem(Relocation $relocation, array $itemData, User $actor): RelocationItem
    {
        $this->ensureItemsEditable($relocation);

        return DB::transaction(function () use ($relocation, $itemData) {
            $item = $this->buildItemPayload($relocation->id, $itemData);
            return RelocationItem::create($item);
        });
    }

    /**
     * Atualiza item existente. Em estados pré-separation, permite tudo.
     * Em in_separation/in_transit, só permite editar qty_separated/received
     * (essas mutações em geral passam pelo TransitionService — este método
     * é fallback para correções manuais via MANAGE_RELOCATIONS).
     */
    public function updateItem(RelocationItem $item, array $data, User $actor): RelocationItem
    {
        $relocation = $item->relocation;

        if (! $relocation || $relocation->is_deleted) {
            throw ValidationException::withMessages([
                'relocation' => 'Remanejo não disponível para edição.',
            ]);
        }

        unset($data['relocation_id'], $data['dispatched_quantity'], $data['received_quantity']);

        $item->fill($data);
        $item->save();

        return $item->fresh();
    }

    public function removeItem(RelocationItem $item): void
    {
        $relocation = $item->relocation;

        if ($relocation) {
            $this->ensureItemsEditable($relocation);
        }

        $item->delete();
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    /**
     * @throws ValidationException
     */
    protected function validateStores(?int $originId, ?int $destinationId): void
    {
        if (! $originId || ! $destinationId) {
            throw ValidationException::withMessages([
                'origin_store_id' => 'Loja origem e destino são obrigatórias.',
            ]);
        }

        if ($originId === $destinationId) {
            throw ValidationException::withMessages([
                'destination_store_id' => 'Loja destino deve ser diferente da loja origem.',
            ]);
        }

        $stores = Store::whereIn('id', [$originId, $destinationId])
            ->get(['id', 'code', 'network_id'])
            ->keyBy('id');

        if ($stores->count() < 2) {
            throw ValidationException::withMessages([
                'origin_store_id' => 'Loja origem ou destino não encontrada.',
            ]);
        }

        $origin = $stores->get($originId);
        $destination = $stores->get($destinationId);

        // Regra de negócio: lojas só transferem dentro da mesma rede
        // comercial (Arezzo→Arezzo, Schutz→Schutz, etc).
        if ($origin->network_id !== $destination->network_id) {
            throw ValidationException::withMessages([
                'destination_store_id' => 'Lojas devem ser da mesma rede comercial. '.
                    "Origem ({$origin->code}) e destino ({$destination->code}) estão em redes diferentes.",
            ]);
        }
    }

    /**
     * Valida que a soma das qty_requested por barcode não excede o saldo
     * efetivo da loja origem (CIGAM - já comprometido em outros remanejos
     * abertos). Se CIGAM offline, pula silenciosamente — sem dados pra
     * validar, melhor permitir do que bloquear arbitrariamente.
     *
     * @param  array<int, array{barcode?: string|null, qty_requested?: int|string}>  $items
     * @throws ValidationException
     */
    protected function validateStockAvailability(int $originStoreId, array $items, ?int $excludeRelocationId = null): void
    {
        if (empty($items) || ! $this->cigam->isAvailable()) {
            return;
        }

        $originCode = Store::where('id', $originStoreId)->value('code');
        if (! $originCode) {
            return;
        }

        // Soma qty_requested por barcode (caso o usuário tenha colocado o
        // mesmo produto em 2 linhas — raro, mas suportado).
        $byBarcode = [];
        foreach ($items as $it) {
            $bc = trim((string) ($it['barcode'] ?? ''));
            if ($bc === '') continue;
            $byBarcode[$bc] = ($byBarcode[$bc] ?? 0) + (int) ($it['qty_requested'] ?? 0);
        }
        if (empty($byBarcode)) {
            return;
        }

        $barcodes = array_keys($byBarcode);

        // Saldo CIGAM (já indexado por barcode + refauxiliar).
        $stockRows = $this->cigam->availableForBarcodes(
            $barcodes,
            onlyStoreCodes: [$originCode],
        );
        $cigamByBarcode = [];
        foreach ($stockRows as $row) {
            $cigamByBarcode[$row->cod_barra] = ($cigamByBarcode[$row->cod_barra] ?? 0) + (int) $row->saldo;
            if (! empty($row->refauxiliar) && $row->refauxiliar !== $row->cod_barra) {
                $cigamByBarcode[$row->refauxiliar] = ($cigamByBarcode[$row->refauxiliar] ?? 0) + (int) $row->saldo;
            }
        }

        $committedByBarcode = $this->committed->committedByBarcode(
            $originStoreId,
            $barcodes,
            $excludeRelocationId,
        );

        $errors = [];
        foreach ($byBarcode as $bc => $req) {
            $cigamQty = $cigamByBarcode[$bc] ?? 0;
            $reservedQty = $committedByBarcode[$bc] ?? 0;
            $effective = max(0, $cigamQty - $reservedQty);
            if ($req > $effective) {
                if ($reservedQty > 0) {
                    $errors[] = "Produto {$bc}: solicitado {$req} unidades, mas saldo efetivo é {$effective} "
                        ."(CIGAM tem {$cigamQty}, sendo {$reservedQty} já reservado(s) em outro(s) remanejo(s) aberto(s) da mesma loja).";
                } else {
                    $errors[] = "Produto {$bc}: solicitado {$req} unidades, mas saldo CIGAM é {$cigamQty}.";
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages([
                'items' => $errors,
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function ensureItemsEditable(Relocation $relocation): void
    {
        if ($relocation->is_deleted || $relocation->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Remanejo em estado terminal não permite alteração de itens.',
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function persistItems(Relocation $relocation, array $items): void
    {
        foreach ($items as $itemData) {
            $payload = $this->buildItemPayload($relocation->id, $itemData);
            RelocationItem::create($payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildItemPayload(int $relocationId, array $data): array
    {
        $reference = trim((string) ($data['product_reference'] ?? ''));
        if ($reference === '') {
            throw ValidationException::withMessages([
                'product_reference' => 'Referência do produto é obrigatória.',
            ]);
        }

        $qtyRequested = (int) ($data['qty_requested'] ?? 1);
        if ($qtyRequested <= 0) {
            throw ValidationException::withMessages([
                'qty_requested' => 'Quantidade solicitada deve ser maior que zero.',
            ]);
        }

        return [
            'relocation_id' => $relocationId,
            'product_id' => $data['product_id'] ?? null,
            'product_reference' => $reference,
            'product_name' => $data['product_name'] ?? null,
            'product_color' => $data['product_color'] ?? null,
            'size' => $data['size'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'qty_requested' => $qtyRequested,
            'qty_separated' => 0,
            'qty_received' => 0,
            'dispatched_quantity' => 0,
            'received_quantity' => 0,
            'observations' => $data['observations'] ?? null,
        ];
    }
}
