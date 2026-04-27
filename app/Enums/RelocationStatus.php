<?php

namespace App\Enums;

/**
 * Ciclo de vida de uma solicitação de remanejo (transferência entre lojas
 * solicitada por planejamento/logística). 8 estados:
 *
 *   draft (Rascunho)
 *       ├──► requested (Solicitado)
 *       │       ├──► approved (Aprovado pelo Planejamento)
 *       │       │       ├──► in_separation (Em Separação na Origem)
 *       │       │       │       ├──► in_transit (Em Trânsito) — NF obrigatória
 *       │       │       │       │       ├──► completed (Recebido OK) ← terminal
 *       │       │       │       │       └──► partial (Recebido com Divergência) ← terminal
 *       │       │       │       └──► cancelled
 *       │       │       └──► cancelled
 *       │       ├──► rejected ← terminal
 *       │       └──► cancelled
 *       └──► cancelled
 *
 * Regras adicionais (validadas em RelocationTransitionService):
 *  - draft → requested exige CREATE_RELOCATIONS
 *  - requested → approved|rejected exige APPROVE_RELOCATIONS
 *  - in_separation → in_transit exige SEPARATE_RELOCATIONS + invoice_number presente
 *  - in_transit → completed|partial exige RECEIVE_RELOCATIONS
 *  - * → cancelled exige note (motivo) e só é permitido pré-in_transit (após
 *    in_transit há Transfer em jogo, exige fluxo de devolução fora do escopo)
 *
 * Quando entra em in_transit, RelocationTransitionService cria Transfer
 * (transfer_type='relocation') com FK bidirecional. Antes disso, é só
 * solicitação interna sem reflexo no módulo de transferências físicas.
 *
 * O RelocationCigamMatcher (command every 15min) reconcilia movements
 * code=5+entry_exit='E' na loja destino com a NF do remanejo, transitando
 * automaticamente para `completed` quando bate.
 */
enum RelocationStatus: string
{
    case DRAFT = 'draft';
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case IN_SEPARATION = 'in_separation';
    case IN_TRANSIT = 'in_transit';
    case COMPLETED = 'completed';
    case PARTIAL = 'partial';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::REQUESTED => 'Solicitado',
            self::APPROVED => 'Aprovado',
            self::IN_SEPARATION => 'Em Separação',
            self::IN_TRANSIT => 'Em Trânsito',
            self::COMPLETED => 'Concluído',
            self::PARTIAL => 'Recebido Parcial',
            self::REJECTED => 'Rejeitado',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::REQUESTED => 'warning',
            self::APPROVED => 'info',
            self::IN_SEPARATION => 'purple',
            self::IN_TRANSIT => 'indigo',
            self::COMPLETED => 'success',
            self::PARTIAL => 'orange',
            self::REJECTED => 'danger',
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
                self::REQUESTED,
                self::CANCELLED,
            ],
            self::REQUESTED => [
                self::APPROVED,
                self::REJECTED,
                self::CANCELLED,
            ],
            self::APPROVED => [
                self::IN_SEPARATION,
                self::CANCELLED,
            ],
            self::IN_SEPARATION => [
                self::IN_TRANSIT,
                self::CANCELLED,
            ],
            self::IN_TRANSIT => [
                self::COMPLETED,
                self::PARTIAL,
            ],
            self::COMPLETED, self::PARTIAL, self::REJECTED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::PARTIAL,
            self::REJECTED,
            self::CANCELLED,
        ], true);
    }

    /**
     * Estados em que o remanejo ainda não tem Transfer físico atrelado.
     * Útil pra decidir se cancelamento é permitido sem fluxo de devolução.
     */
    public function isPreTransit(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::REQUESTED,
            self::APPROVED,
            self::IN_SEPARATION,
        ], true);
    }

    /**
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::COMPLETED, self::PARTIAL, self::REJECTED, self::CANCELLED];
    }

    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [
            self::DRAFT,
            self::REQUESTED,
            self::APPROVED,
            self::IN_SEPARATION,
            self::IN_TRANSIT,
        ];
    }

    /**
     * Status que ainda comprometem saldo da loja origem — ou seja, a NF de
     * saída ainda não foi emitida e o CIGAM não baixou esses itens. Usado
     * pra detectar overcommit do mesmo produto em múltiplos remanejos.
     *
     * IN_TRANSIT é deliberadamente excluído: nesse estágio a NF já foi
     * emitida e o CIGAM já refletiu a baixa — incluir geraria desconto
     * duplo do saldo apresentado.
     *
     * @return array<int, self>
     */
    public static function committingStock(): array
    {
        return [
            self::DRAFT,
            self::REQUESTED,
            self::APPROVED,
            self::IN_SEPARATION,
        ];
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
