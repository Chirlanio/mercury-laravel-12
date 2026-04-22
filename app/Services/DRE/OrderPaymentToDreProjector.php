<?php

namespace App\Services\DRE;

use App\Enums\AccountGroup;
use App\Models\DreActual;
use App\Models\DrePeriodClosing;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\DB;

/**
 * Projeta OrderPayment (status=done) para dre_actuals.
 *
 * `docs/dre-arquitetura.md §2.5` — OrderPayment é a fonte principal das
 * despesas contabilizadas (competence_date + accounting_class + cost_center
 * + store + total_value). A projeção é 1:1 por `(source=ORDER_PAYMENT,
 * source_id=op.id)` — upsert idempotente.
 *
 * Convenção de sinal (§2.5.1): receita positiva, despesa negativa. Derivada
 * do `account_group` da conta contábil:
 *   - G3 Receitas      → amount = +abs(total_value)
 *   - G4 Custos/Desp   → amount = -abs(total_value)
 *   - G5 Resultado     → amount = -abs(total_value) (convenção projeto)
 *   - G1/G2 Ativo/Pass → exception (OP não deveria usar essas contas)
 *
 * `reported_in_closed_period` é marcado `true` quando `competence_date` cai
 * em mês já fechado — indica à tela de reabertura que há movimento novo
 * fora do snapshot (resposta #23 da arquitetura).
 */
class OrderPaymentToDreProjector
{
    /**
     * Projeta (upsert) ou retorna null se o OP não qualifica.
     */
    public function project(OrderPayment $op): ?DreActual
    {
        if ($op->status !== OrderPayment::STATUS_DONE) {
            return null;
        }

        if ($op->accounting_class_id === null) {
            // Sem conta contábil não dá pra projetar. Não é exception — é
            // dado incompleto comum durante o workflow.
            return null;
        }

        $account = $op->accountingClass;
        if (! $account) {
            return null;
        }

        $accountGroup = $this->accountGroupValue($account);
        $amount = $this->convertSign((float) $op->total_value, $accountGroup);

        // Competence_date é a data fiscal. Fallback defensivo para date_payment
        // quando competence ausente (OPs legados sem essa coluna populada).
        $entryDate = $op->competence_date
            ?? $op->date_payment
            ?? now();

        if (is_object($entryDate) && method_exists($entryDate, 'format')) {
            $entryDate = $entryDate->format('Y-m-d');
        }

        $reportedInClosed = $this->isInClosedPeriod($entryDate);

        $attrs = [
            'entry_date' => $entryDate,
            'chart_of_account_id' => (int) $op->accounting_class_id,
            'cost_center_id' => $op->cost_center_id,
            'store_id' => $op->store_id,
            'amount' => $amount,
            'source' => DreActual::SOURCE_ORDER_PAYMENT,
            'source_type' => OrderPayment::class,
            'source_id' => $op->id,
            'document' => $op->number_nf,
            'description' => $this->truncate($op->description, 500),
            'reported_in_closed_period' => $reportedInClosed,
        ];

        return DB::transaction(function () use ($op, $attrs) {
            return DreActual::updateOrCreate(
                [
                    'source_type' => OrderPayment::class,
                    'source_id' => $op->id,
                ],
                $attrs,
            );
        });
    }

    /**
     * Remove a projeção desse OrderPayment (se existir). Chamado quando o
     * OP sai de `done` ou é deletado.
     */
    public function unproject(OrderPayment $op): void
    {
        DreActual::query()
            ->where('source_type', OrderPayment::class)
            ->where('source_id', $op->id)
            ->delete();
    }

    /**
     * Limpa todas as projeções ORDER_PAYMENT e reprojeta todos os OPs com
     * status=done. Usado pelo command `dre:rebuild-actuals` como
     * reconciliação defensiva (scheduled semanal no routes/console.php).
     */
    public function rebuild(): RebuildReport
    {
        $report = new RebuildReport();

        $report->truncated = DreActual::query()
            ->where('source', DreActual::SOURCE_ORDER_PAYMENT)
            ->delete();

        OrderPayment::query()
            ->where('status', OrderPayment::STATUS_DONE)
            ->whereNull('deleted_at')
            ->chunkById(500, function ($batch) use ($report) {
                foreach ($batch as $op) {
                    try {
                        $result = $this->project($op);
                        if ($result) {
                            $report->projected++;
                        } else {
                            $report->addSkip(sprintf(
                                'OP id=%d sem conta contábil ou status != done.',
                                $op->id
                            ));
                        }
                    } catch (\Throwable $e) {
                        $report->addSkip(sprintf(
                            'OP id=%d: %s',
                            $op->id,
                            $e->getMessage()
                        ));
                    }
                }
            });

        return $report;
    }

    /**
     * Conversão de sinal. Despesa vira negativa, receita positiva.
     *
     * @throws \DomainException para grupos 1/2 (Ativo/Passivo) — não pertencem à DRE.
     */
    public function convertSign(float $absOrSigned, int $accountGroup): float
    {
        $abs = abs($absOrSigned);

        return match (true) {
            $accountGroup === 3 => +$abs,                      // Receitas
            $accountGroup === 4, $accountGroup === 5 => -$abs, // Custos/Despesas/Resultado
            default => throw new \DomainException(sprintf(
                'Conta contábil de grupo %d (%s) não pode projetar para DRE. '
                .'OrderPayment precisa apontar para conta de resultado (grupos 3, 4 ou 5).',
                $accountGroup,
                $accountGroup === 1 ? 'Ativo' : ($accountGroup === 2 ? 'Passivo' : 'desconhecido')
            )),
        };
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    /** Normaliza account_group para int (aceita enum cast ou coluna bruta). */
    private function accountGroupValue($account): int
    {
        $raw = $account->account_group;

        if ($raw instanceof AccountGroup) {
            return $raw->value;
        }

        return (int) $raw;
    }

    private function isInClosedPeriod(string $entryDate): bool
    {
        $lastClosed = DrePeriodClosing::query()
            ->whereNull('reopened_at')
            ->orderByDesc('closed_up_to_date')
            ->value('closed_up_to_date');

        if ($lastClosed === null) {
            return false;
        }

        $lastClosedStr = $lastClosed instanceof \DateTimeInterface
            ? $lastClosed->format('Y-m-d')
            : (string) $lastClosed;

        return $entryDate <= $lastClosedStr;
    }

    private function truncate(?string $text, int $limit): ?string
    {
        if ($text === null) {
            return null;
        }

        return strlen($text) <= $limit ? $text : substr($text, 0, $limit - 3).'...';
    }
}
