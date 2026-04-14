<?php

namespace App\Enums;

/**
 * Ciclo de vida da vaga. Replica a state machine da v1 (adms_sits_vacancy):
 *
 *   open (Aberta) → processing (Em Processamento) → in_admission (Em Admissão) → finalized (Finalizada)
 *                                                                              ↘ cancelled (Cancelada)
 *
 * Regras de transição adicionais (validadas em VacancyTransitionService):
 *  - open → processing exige recruiter_id
 *  - in_admission → finalized exige hired_employee_id + date_admission
 *    (normalmente preenchido via pré-cadastro de funcionário no modal)
 *  - * → cancelled exige note (motivo do cancelamento)
 */
enum VacancyStatus: string
{
    case OPEN = 'open';
    case PROCESSING = 'processing';
    case IN_ADMISSION = 'in_admission';
    case FINALIZED = 'finalized';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::PROCESSING => 'Em Processamento',
            self::IN_ADMISSION => 'Em Admissão',
            self::FINALIZED => 'Finalizada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'info',
            self::PROCESSING => 'warning',
            self::IN_ADMISSION => 'purple',
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
            self::OPEN => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::OPEN, self::IN_ADMISSION, self::CANCELLED],
            self::IN_ADMISSION => [self::PROCESSING, self::FINALIZED, self::CANCELLED],
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
        return in_array($this, [self::FINALIZED, self::CANCELLED], true);
    }

    /**
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::FINALIZED, self::CANCELLED];
    }

    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::OPEN, self::PROCESSING, self::IN_ADMISSION];
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
