<?php

namespace App\Enums;

/**
 * Ciclo de vida da prestação de contas (accountability) — paralelo e independente
 * do TravelExpenseStatus. A prestação só "abre" quando a verba é aprovada
 * (TravelExpenseStatus::APPROVED), portanto começa em PENDING.
 *
 *   pending (Aguardando)
 *       └──► in_progress (Em andamento, há itens lançados)
 *               ├──► pending (sem itens, voltou)
 *               ├──► submitted (Enviada pra aprovação)
 *               │      ├──► in_progress (devolveu pra correção)
 *               │      ├──► approved ← terminal (TravelExpense → finalized)
 *               │      └──► rejected (volta pra in_progress)
 *
 * - pending → in_progress: ao adicionar primeiro item (automático)
 * - in_progress → submitted: solicitante/beneficiado encerra prestação
 * - submitted → approved: APPROVE_TRAVEL_EXPENSES (Financeiro)
 * - submitted → rejected: APPROVE_TRAVEL_EXPENSES (volta pra in_progress)
 */
enum AccountabilityStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Aguardando Lançamento',
            self::IN_PROGRESS => 'Em Andamento',
            self::SUBMITTED => 'Aguardando Aprovação',
            self::APPROVED => 'Aprovada',
            self::REJECTED => 'Recusada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::IN_PROGRESS => 'info',
            self::SUBMITTED => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::IN_PROGRESS],
            self::IN_PROGRESS => [self::PENDING, self::SUBMITTED],
            self::SUBMITTED => [self::IN_PROGRESS, self::APPROVED, self::REJECTED],
            self::REJECTED => [self::IN_PROGRESS],
            self::APPROVED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::APPROVED;
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
