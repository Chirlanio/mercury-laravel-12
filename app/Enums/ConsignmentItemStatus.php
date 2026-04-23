<?php

namespace App\Enums;

/**
 * Status derivado de cada item de uma consignação.
 *
 * Nunca é definido manualmente — é recalculado a partir das quantidades
 * (`quantity`, `returned_quantity`, `sold_quantity`, `lost_quantity`)
 * pelo método `ConsignmentItem::refreshDerivedStatus()`.
 *
 *  - pending: nenhuma resolução ainda (returned + sold + lost = 0)
 *  - partial: houve alguma resolução mas quantity ainda pendente > 0
 *  - returned: tudo voltou fisicamente (returned_quantity = quantity)
 *  - sold: tudo foi vendido (sold_quantity = quantity)
 *  - mixed: combinação de retorno + venda + perda zerou o pendente
 *           (≥ 2 categorias com > 0) — caso comum: cliente devolveu
 *           parte e comprou o restante
 *  - lost: tudo foi declarado perdido (shrinkage) — destinatário não
 *          devolveu nem comprou; só atingível via finalização forçada
 *          com COMPLETE_CONSIGNMENT + justificativa
 */
enum ConsignmentItemStatus: string
{
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case RETURNED = 'returned';
    case SOLD = 'sold';
    case MIXED = 'mixed';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PARTIAL => 'Parcial',
            self::RETURNED => 'Devolvido',
            self::SOLD => 'Vendido',
            self::MIXED => 'Devolvido + Vendido',
            self::LOST => 'Perdido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PARTIAL => 'info',
            self::RETURNED => 'success',
            self::SOLD => 'success',
            self::MIXED => 'success',
            self::LOST => 'danger',
        };
    }

    /**
     * Indica se o item ainda tem quantidade pendente (não resolvida).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::PENDING, self::PARTIAL], true);
    }

    /**
     * Deriva o status a partir das quantidades do item.
     *
     * Regras:
     *  - todos zerados de resolução → PENDING
     *  - resolvido integralmente em 1 só categoria → RETURNED/SOLD/LOST
     *  - resolvido integralmente mas em ≥ 2 categorias → MIXED
     *  - parcialmente resolvido → PARTIAL
     */
    public static function derive(
        int $quantity,
        int $returned,
        int $sold,
        int $lost,
    ): self {
        $resolved = $returned + $sold + $lost;

        if ($resolved === 0) {
            return self::PENDING;
        }

        if ($resolved < $quantity) {
            return self::PARTIAL;
        }

        // resolved === quantity (assumindo callers validam resolved <= quantity)
        $categoriesWithValue = ($returned > 0 ? 1 : 0)
            + ($sold > 0 ? 1 : 0)
            + ($lost > 0 ? 1 : 0);

        if ($categoriesWithValue > 1) {
            return self::MIXED;
        }

        if ($returned > 0) {
            return self::RETURNED;
        }

        if ($sold > 0) {
            return self::SOLD;
        }

        return self::LOST;
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
}
