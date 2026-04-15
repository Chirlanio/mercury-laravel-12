<?php

namespace App\Enums;

/**
 * Ciclo de vida de uma ordem de compra de coleção.
 *
 * Replica o fluxo real do varejo de moda v1 (adms_sits_orders):
 *
 *   pending (Pendente) ──► invoiced (Faturado) ──► delivered (Entregue)  ← terminal
 *       │                      │
 *       ├──► partial_invoiced (Faturado Parcial) ──► invoiced
 *       │                      │
 *       └──► cancelled (Cancelado) ──► pending (reabertura, só admin)
 *
 * Semântica:
 *  - pending: ordem criada, aguardando NF do fornecedor
 *  - invoiced: NF emitida pelo fornecedor, aguardando entrega
 *  - partial_invoiced: NF parcial (parte dos itens faturados)
 *  - delivered: mercadoria recebida na loja (estado terminal)
 *  - cancelled: ordem cancelada (pode ser reaberta por admin)
 *
 * Regras de transição adicionais (validadas em PurchaseOrderTransitionService):
 *  - pending → invoiced/partial_invoiced exige permission APPROVE_PURCHASE_ORDERS
 *  - * → cancelled exige CANCEL_PURCHASE_ORDERS + note
 *  - cancelled → pending exige CANCEL_PURCHASE_ORDERS (reabertura)
 *  - invoiced → delivered pode ser disparado automaticamente pelo
 *    PurchaseOrderReceiptService quando quantity_received == quantity_ordered
 *    em todos os itens
 */
enum PurchaseOrderStatus: string
{
    case PENDING = 'pending';
    case INVOICED = 'invoiced';
    case PARTIAL_INVOICED = 'partial_invoiced';
    case CANCELLED = 'cancelled';
    case DELIVERED = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::INVOICED => 'Faturado',
            self::PARTIAL_INVOICED => 'Faturado Parcial',
            self::CANCELLED => 'Cancelado',
            self::DELIVERED => 'Entregue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::INVOICED => 'info',
            self::PARTIAL_INVOICED => 'purple',
            self::CANCELLED => 'danger',
            self::DELIVERED => 'success',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::INVOICED, self::PARTIAL_INVOICED, self::CANCELLED],
            self::INVOICED => [self::DELIVERED, self::CANCELLED],
            self::PARTIAL_INVOICED => [self::INVOICED, self::CANCELLED],
            self::CANCELLED => [self::PENDING],
            self::DELIVERED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::DELIVERED;
    }

    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::PENDING, self::INVOICED, self::PARTIAL_INVOICED];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->color()])
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function transitionMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [
                $c->value => array_map(fn (self $t) => $t->value, $c->allowedTransitions()),
            ])
            ->all();
    }
}
