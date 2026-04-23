<?php

namespace App\Enums;

/**
 * Ciclo de vida de uma consignação (cabeçalho).
 *
 *   draft (Rascunho — criada sem NF de saída ainda emitida)
 *       ├──► pending (Pendente — NF de saída emitida, produtos com destinatário)
 *       │       ├──► partially_returned (Parcialmente retornada — parte voltou ou foi vendida)
 *       │       │       ├──► completed (Finalizada — tudo resolvido)
 *       │       │       ├──► overdue (prazo venceu com pendências)
 *       │       │       └──► cancelled
 *       │       ├──► completed
 *       │       ├──► overdue
 *       │       └──► cancelled
 *       └──► cancelled
 *
 * Regras adicionais (validadas em ConsignmentTransitionService):
 *  - qualquer transição → completed: todos os itens devem estar resolvidos
 *    (returned + sold + lost = quantity). Itens ainda pendentes bloqueiam
 *    a finalização a menos que sejam marcados como lost (shrinkage).
 *  - overdue é automático via command `consignments:mark-overdue` (daily
 *    06:00) quando expected_return_date < now() e há itens pendentes.
 *  - * → cancelled: exige CANCEL_CONSIGNMENT + motivo.
 *  - terminais: completed, cancelled (não voltam).
 */
enum ConsignmentStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PARTIALLY_RETURNED = 'partially_returned';
    case OVERDUE = 'overdue';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::PENDING => 'Pendente',
            self::PARTIALLY_RETURNED => 'Parcialmente retornada',
            self::OVERDUE => 'Em atraso',
            self::COMPLETED => 'Finalizada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'info',
            self::PARTIALLY_RETURNED => 'warning',
            self::OVERDUE => 'danger',
            self::COMPLETED => 'success',
            self::CANCELLED => 'gray',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [
                self::PENDING,
                self::CANCELLED,
            ],
            self::PENDING => [
                self::PARTIALLY_RETURNED,
                self::COMPLETED,
                self::OVERDUE,
                self::CANCELLED,
            ],
            self::PARTIALLY_RETURNED => [
                self::COMPLETED,
                self::OVERDUE,
                self::CANCELLED,
            ],
            self::OVERDUE => [
                self::PARTIALLY_RETURNED,
                self::COMPLETED,
                self::CANCELLED,
            ],
            self::COMPLETED => [],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * Estados considerados "em aberto" — consignação ainda tem itens
     * que podem retornar, serem vendidos ou declarados como perdidos.
     * Usado em ConsignmentService::ensureRecipientEligibility (M9) para
     * bloquear novo cadastro quando destinatário tem overdue aberto.
     *
     * @return array<int, self>
     */
    public static function openStates(): array
    {
        return [
            self::DRAFT,
            self::PENDING,
            self::PARTIALLY_RETURNED,
            self::OVERDUE,
        ];
    }

    /**
     * Estados que bloqueiam novo cadastro para o destinatário (M9).
     * Apenas OVERDUE bloqueia por padrão — pending e partially_returned
     * ainda têm prazo vigente e são cadastros legítimos em paralelo.
     *
     * @return array<int, self>
     */
    public static function blockingStates(): array
    {
        return [self::OVERDUE];
    }

    /**
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::COMPLETED, self::CANCELLED];
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
