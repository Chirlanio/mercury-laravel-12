<?php

namespace App\Enums;

/**
 * Ciclo de vida de uma solicitação de verba de viagem.
 *
 *   draft (Rascunho)
 *       ├──► submitted (Solicitada)
 *       │         ├──► draft (devolver pra rascunho)
 *       │         ├──► approved (Aprovada) ──► finalized (Finalizada) ← terminal
 *       │         │                          └──► cancelled (Cancelada) ← terminal
 *       │         ├──► rejected (Rejeitada) ← terminal
 *       │         └──► cancelled
 *       └──► cancelled
 *
 * - draft → submitted: dono da solicitação envia pra análise
 * - submitted → approved: APPROVE_TRAVEL_EXPENSES (Financeiro)
 * - submitted → rejected: APPROVE_TRAVEL_EXPENSES (com motivo)
 * - approved → finalized: APPROVE_TRAVEL_EXPENSES depois que prestação for aceita
 * - * → cancelled: APPROVE_TRAVEL_EXPENSES ou MANAGE_TRAVEL_EXPENSES (com motivo)
 */
enum TravelExpenseStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case FINALIZED = 'finalized';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::SUBMITTED => 'Solicitada',
            self::APPROVED => 'Aprovada',
            self::REJECTED => 'Rejeitada',
            self::FINALIZED => 'Finalizada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'warning',
            self::APPROVED => 'info',
            self::REJECTED => 'danger',
            self::FINALIZED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [
                self::SUBMITTED,
                self::CANCELLED,
            ],
            self::SUBMITTED => [
                self::DRAFT,
                self::APPROVED,
                self::REJECTED,
                self::CANCELLED,
            ],
            self::APPROVED => [
                self::FINALIZED,
                self::CANCELLED,
            ],
            self::REJECTED => [],
            self::FINALIZED => [],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminal(), true);
    }

    /**
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::REJECTED, self::FINALIZED, self::CANCELLED];
    }

    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::DRAFT, self::SUBMITTED, self::APPROVED];
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
