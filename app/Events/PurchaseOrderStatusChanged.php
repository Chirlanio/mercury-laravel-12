<?php

namespace App\Events;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após uma transição de status de PurchaseOrder bem-sucedida,
 * pelo PurchaseOrderTransitionService. A mutação já foi commitada no banco
 * quando este evento dispara.
 *
 * Consumidores:
 *  - NotifyPurchaseOrderStakeholders: cria notificações database para
 *    usuários com MANAGE_PURCHASE_ORDERS + gerentes da store da ordem
 *  - (Fase 3) GeneratePurchaseOrderPayments: quando to=invoiced e
 *    order.auto_generate_payments=true, cria order_payments a partir de
 *    payment_terms_raw
 *  - (Fase 2) AutoCloseOrderOnFullReceipt: disparado quando todos os itens
 *    estão recebidos (chama transition para DELIVERED)
 */
class PurchaseOrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PurchaseOrder $order,
        public readonly PurchaseOrderStatus $fromStatus,
        public readonly PurchaseOrderStatus $toStatus,
        public readonly User $actor,
        public readonly ?string $note = null,
    ) {}
}
