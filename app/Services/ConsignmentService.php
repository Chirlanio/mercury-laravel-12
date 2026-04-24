<?php

namespace App\Services;

use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use App\Enums\Permission;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD + regras de negócio da Consignação.
 *
 * Mutações de status passam pelo ConsignmentTransitionService. Este
 * service NUNCA seta `status` diretamente fora dos métodos `create`
 * (que inicializa como draft) e `issue` (que delega ao transition).
 *
 * Regras implementadas:
 *  - M8: todo item tem product_id NOT NULL — validado em
 *        addItem/syncItems. Lançamento do DB é o fallback final.
 *  - M9: ensureRecipientEligibility bloqueia cadastro quando
 *        destinatário tem consignação em `overdue`. Override via
 *        OVERRIDE_CONSIGNMENT_LOCK com justificativa.
 *  - Regras por tipo: CLIENTE exige employee_id. E-commerce/Influencer
 *        aceitam employee_id null.
 */
class ConsignmentService
{
    public function __construct(
        protected ConsignmentTransitionService $transitions,
    ) {
    }

    /**
     * Cria uma consignação nova em status `draft` ou `pending`. Se `items`
     * for informado, adiciona após o insert e recalcula totais.
     *
     * @param  array{
     *   type: string,
     *   store_id: int,
     *   employee_id?: ?int,
     *   recipient_name: string,
     *   recipient_document?: ?string,
     *   recipient_phone?: ?string,
     *   recipient_email?: ?string,
     *   outbound_invoice_number: string,
     *   outbound_invoice_date: string,
     *   outbound_store_code?: ?string,
     *   return_period_days?: ?int,
     *   expected_return_date?: ?string,
     *   notes?: ?string,
     *   items?: array<int, array<string, mixed>>,
     *   issue_now?: bool,
     *   override_lock_reason?: ?string,
     * }  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor): Consignment
    {
        $type = ConsignmentType::from($data['type']);
        $store = Store::query()->findOrFail($data['store_id']);

        // Regra por tipo — CLIENTE exige consultor responsável
        if ($type->requiresEmployee() && empty($data['employee_id'])) {
            throw ValidationException::withMessages([
                'employee_id' => 'Consignação para cliente exige consultor(a) responsável.',
            ]);
        }

        // Documento do destinatário — limpo + validado (11 CPF / 14 CNPJ)
        $recipientDoc = $data['recipient_document'] ?? null;
        $recipientDocClean = $recipientDoc ? preg_replace('/\D/', '', $recipientDoc) : null;
        if ($recipientDocClean !== null && $recipientDocClean !== '' && ! in_array(strlen($recipientDocClean), [11, 14], true)) {
            throw ValidationException::withMessages([
                'recipient_document' => 'Documento deve conter 11 (CPF) ou 14 (CNPJ) dígitos.',
            ]);
        }

        // Regra M9: bloqueio de inadimplência
        $this->ensureRecipientEligibility(
            $recipientDocClean,
            $actor,
            $data['override_lock_reason'] ?? null,
        );

        // Regra M20: limite configurável por cliente (peças / valor)
        $this->ensureWithinCustomerLimits(
            $data['customer_id'] ?? null,
            $data['items'] ?? [],
            $actor,
            $data['override_lock_reason'] ?? null,
        );

        $returnPeriod = (int) ($data['return_period_days'] ?? $type->defaultReturnPeriodDays());
        $outboundDate = $data['outbound_invoice_date'];
        $expectedReturn = $data['expected_return_date']
            ?? (new \DateTime($outboundDate))->modify("+{$returnPeriod} days")->format('Y-m-d');

        return DB::transaction(function () use (
            $data, $type, $store, $actor, $recipientDoc, $recipientDocClean,
            $outboundDate, $expectedReturn, $returnPeriod
        ) {
            $consignment = Consignment::create([
                'type' => $type->value,
                'store_id' => $store->id,
                'employee_id' => $data['employee_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'recipient_name' => strtoupper(trim($data['recipient_name'])),
                'recipient_document' => $recipientDoc,
                'recipient_document_clean' => $recipientDocClean,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'recipient_email' => $data['recipient_email'] ?? null,
                'outbound_invoice_number' => trim($data['outbound_invoice_number']),
                'outbound_invoice_date' => $outboundDate,
                'outbound_store_code' => $data['outbound_store_code'] ?? $store->code,
                'expected_return_date' => $expectedReturn,
                'return_period_days' => $returnPeriod,
                'status' => ConsignmentStatus::DRAFT->value,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            // Se veio override_lock_reason, grava no histórico inicial
            if (! empty($data['override_lock_reason'])) {
                $consignment->statusHistory()->create([
                    'consignment_id' => $consignment->id,
                    'from_status' => null,
                    'to_status' => ConsignmentStatus::DRAFT->value,
                    'changed_by_user_id' => $actor->id,
                    'note' => 'Override de bloqueio por inadimplência: '.$data['override_lock_reason'],
                    'context' => ['override_lock' => true],
                    'created_at' => now(),
                ]);
            }

            // Items (regra M8 — product_id obrigatório)
            foreach (($data['items'] ?? []) as $item) {
                $this->addItem($consignment, $item);
            }

            $this->refreshTotals($consignment);

            // Emite NF automaticamente se solicitado (draft → pending)
            if (! empty($data['issue_now'])) {
                $consignment = $this->transitions->issue($consignment->fresh(), $actor);
            }

            return $consignment->fresh(['items', 'statusHistory']);
        });
    }

    /**
     * Adiciona um item à consignação. Regra M8: `product_id` obrigatório
     * — se o item traz só `reference`/`barcode`, resolvemos no catálogo.
     *
     * @param  array{
     *   product_id?: ?int,
     *   product_variant_id?: ?int,
     *   reference?: ?string,
     *   barcode?: ?string,
     *   size_label?: ?string,
     *   size_cigam_code?: ?string,
     *   description?: ?string,
     *   quantity: int,
     *   unit_value: float,
     *   movement_id?: ?int,
     * }  $data
     *
     * @throws ValidationException
     */
    public function addItem(Consignment $consignment, array $data): ConsignmentItem
    {
        $product = null;
        $variant = null;

        if (! empty($data['product_id'])) {
            $product = Product::query()->find($data['product_id']);
        }

        // Fallback: tenta resolver por reference/barcode (regra M8)
        if (! $product && (! empty($data['reference']) || ! empty($data['barcode']))) {
            $lookup = app(ConsignmentLookupService::class)->resolveProductVariant(
                reference: $data['reference'] ?? null,
                barcode: $data['barcode'] ?? null,
                sizeCigamCode: $data['size_cigam_code'] ?? null,
            );
            if ($lookup) {
                $product = $lookup['product'];
                $variant = $lookup['variant'];
            }
        }

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'Produto não encontrado no catálogo. Cadastre o produto antes de incluir na consignação.',
            ]);
        }

        // Variante (se não resolvida ainda)
        if (! $variant && ! empty($data['product_variant_id'])) {
            $variant = ProductVariant::query()->find($data['product_variant_id']);
        }
        if (! $variant && ! empty($data['size_cigam_code'])) {
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('size_cigam_code', $data['size_cigam_code'])
                ->first();
        }

        $quantity = max(1, (int) $data['quantity']);
        $unitValue = round((float) $data['unit_value'], 2);

        // Se o item veio da NF (movement_id presente), quantidade e valor
        // devem vir do movement — não aceitar valores do payload.
        // Evita que um usuário adultere os números da NF via DevTools.
        if (! empty($data['movement_id'])) {
            $movement = \App\Models\Movement::find($data['movement_id']);
            if ($movement) {
                $quantity = max(1, (int) $movement->quantity);
                $unitValue = round((float) $movement->sale_price, 2);
            }
        }

        return ConsignmentItem::create([
            'consignment_id' => $consignment->id,
            'movement_id' => $data['movement_id'] ?? null,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'reference' => $data['reference'] ?? $product->reference,
            'barcode' => $data['barcode'] ?? $variant?->barcode,
            'size_label' => $data['size_label'] ?? null,
            'size_cigam_code' => $data['size_cigam_code'] ?? $variant?->size_cigam_code,
            'description' => $data['description'] ?? $product->description,
            'quantity' => $quantity,
            'unit_value' => $unitValue,
            'total_value' => round($quantity * $unitValue, 2),
            'returned_quantity' => 0,
            'sold_quantity' => 0,
            'lost_quantity' => 0,
            'status' => 'pending',
        ]);
    }

    /**
     * Regra M9: bloqueia cadastro quando destinatário tem consignação em
     * `overdue` aberto. Override permissionado grava justificativa no
     * histórico.
     *
     * @throws ValidationException
     */
    public function ensureRecipientEligibility(
        ?string $documentClean,
        User $actor,
        ?string $overrideReason = null,
    ): void {
        if (! $documentClean) {
            return;
        }

        $overdueCount = Consignment::query()
            ->whereNull('deleted_at')
            ->forRecipientDocument($documentClean)
            ->whereIn('status', array_map(
                fn (ConsignmentStatus $s) => $s->value,
                ConsignmentStatus::blockingStates(),
            ))
            ->count();

        if ($overdueCount === 0) {
            return;
        }

        // Já existe bloqueio — só segue com override permissionado + motivo
        if ($overrideReason !== null && trim($overrideReason) !== '') {
            if (! $actor->hasPermissionTo(Permission::OVERRIDE_CONSIGNMENT_LOCK->value)) {
                throw ValidationException::withMessages([
                    'override_lock_reason' => 'Você não tem permissão para ignorar o bloqueio por inadimplência.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'recipient_document' => "Este destinatário possui {$overdueCount} consignação(ões) em atraso. Finalize ou cancele as pendentes antes de criar uma nova.",
        ]);
    }

    /**
     * Regra M20: bloqueia cadastro quando a soma de itens ou valor em
     * consignações abertas do cliente (+ a nova) estouraria os tetos
     * configurados em `customers.consignment_max_items` /
     * `customers.consignment_max_value`. Tetos nulos = sem limite.
     *
     * Só se aplica quando a consignação tem FK `customer_id` conhecida —
     * consignações com CPF avulso (sem cadastro) ficam livres do limite.
     * Override via OVERRIDE_CONSIGNMENT_LOCK + justificativa.
     *
     * @param  array<int, array<string, mixed>>  $proposedItems
     *
     * @throws ValidationException
     */
    public function ensureWithinCustomerLimits(
        ?int $customerId,
        array $proposedItems,
        User $actor,
        ?string $overrideReason = null,
    ): void {
        if (! $customerId) {
            return;
        }

        $customer = Customer::find($customerId);
        if (! $customer) {
            return;
        }

        $maxItems = $customer->consignment_max_items;
        $maxValue = $customer->consignment_max_value ? (float) $customer->consignment_max_value : null;

        if (! $maxItems && ! $maxValue) {
            return;
        }

        // Soma do que o cliente já tem em aberto (pendente = saiu menos
        // resolvido). M20 considera TODOS os estados não-terminais
        // (pending/partially_returned/overdue) — diferente de M9 que só
        // dispara em OVERDUE.
        $openStates = [
            ConsignmentStatus::PENDING->value,
            ConsignmentStatus::PARTIALLY_RETURNED->value,
            ConsignmentStatus::OVERDUE->value,
        ];

        $open = Consignment::query()
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->whereIn('status', $openStates)
            ->selectRaw('COALESCE(SUM(outbound_items_count - returned_items_count - sold_items_count - lost_items_count), 0) as pending_items')
            ->selectRaw('COALESCE(SUM(outbound_total_value - returned_total_value - sold_total_value - lost_total_value), 0) as pending_value')
            ->first();

        $pendingItems = (int) ($open->pending_items ?? 0);
        $pendingValue = (float) ($open->pending_value ?? 0);

        $proposedItemsCount = 0;
        $proposedItemsValue = 0.0;
        foreach ($proposedItems as $row) {
            $qty = max(0, (int) ($row['quantity'] ?? 0));
            $unit = max(0.0, (float) ($row['unit_value'] ?? 0));
            $proposedItemsCount += $qty;
            $proposedItemsValue += round($qty * $unit, 2);
        }

        $totalItems = $pendingItems + $proposedItemsCount;
        $totalValue = round($pendingValue + $proposedItemsValue, 2);

        $violations = [];
        if ($maxItems && $totalItems > $maxItems) {
            $violations[] = sprintf(
                'teto de %d peças (atual: %d em aberto + %d nesta = %d)',
                $maxItems, $pendingItems, $proposedItemsCount, $totalItems,
            );
        }
        if ($maxValue && $totalValue > $maxValue) {
            $violations[] = sprintf(
                'teto de R$ %s (atual: R$ %s em aberto + R$ %s nesta = R$ %s)',
                number_format($maxValue, 2, ',', '.'),
                number_format($pendingValue, 2, ',', '.'),
                number_format($proposedItemsValue, 2, ',', '.'),
                number_format($totalValue, 2, ',', '.'),
            );
        }

        if (empty($violations)) {
            return;
        }

        if ($overrideReason !== null && trim($overrideReason) !== '') {
            if (! $actor->hasPermissionTo(Permission::OVERRIDE_CONSIGNMENT_LOCK->value)) {
                throw ValidationException::withMessages([
                    'override_lock_reason' => 'Você não tem permissão para ignorar o limite por cliente.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'customer_id' => 'Cliente excederia '.implode(' e ', $violations).'.',
        ]);
    }

    /**
     * Recalcula totais denormalizados (items_count, total_values) a
     * partir dos items. Chamado após add/update/delete de items e
     * após eventos de retorno/venda/perda. Idempotente.
     */
    public function refreshTotals(Consignment $consignment): Consignment
    {
        $items = $consignment->items()->get();

        $outboundCount = $items->sum('quantity');
        $outboundValue = $items->sum('total_value');

        $returnedCount = $items->sum('returned_quantity');
        $soldCount = $items->sum('sold_quantity');
        $lostCount = $items->sum('lost_quantity');

        $returnedValue = $items->sum(fn ($i) => round($i->returned_quantity * (float) $i->unit_value, 2));
        $soldValue = $items->sum(fn ($i) => round($i->sold_quantity * (float) $i->unit_value, 2));
        $lostValue = $items->sum(fn ($i) => round($i->lost_quantity * (float) $i->unit_value, 2));

        $consignment->update([
            'outbound_items_count' => (int) $outboundCount,
            'outbound_total_value' => round((float) $outboundValue, 2),
            'returned_items_count' => (int) $returnedCount,
            'returned_total_value' => round((float) $returnedValue, 2),
            'sold_items_count' => (int) $soldCount,
            'sold_total_value' => round((float) $soldValue, 2),
            'lost_items_count' => (int) $lostCount,
            'lost_total_value' => round((float) $lostValue, 2),
        ]);

        return $consignment->fresh();
    }

    /**
     * Exclusão lógica (soft delete manual — padrão do projeto). Bloqueado
     * se houver retorno já lançado (preserva auditoria fiscal).
     *
     * @throws ValidationException
     */
    public function delete(Consignment $consignment, User $actor, ?string $reason = null): void
    {
        if (! $actor->hasPermissionTo(Permission::DELETE_CONSIGNMENTS->value)) {
            throw ValidationException::withMessages([
                'delete' => 'Você não tem permissão para excluir consignações.',
            ]);
        }

        if ($consignment->returns()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Não é possível excluir consignação com nota de retorno já lançada.',
            ]);
        }

        $consignment->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->id,
            'deleted_reason' => $reason,
        ]);
    }
}
