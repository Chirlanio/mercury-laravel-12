<?php

namespace App\Notifications;

use App\Models\DrePeriodClosing;
use App\Models\User;
use App\Services\DRE\ReopenReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa usuários com `MANAGE_DRE_PERIODS` que um fechamento foi reaberto.
 *
 * Inclui justificativa + diffs consolidados (até 20 primeiras linhas, link
 * na tela para o full). Propagado por `mail` + `database` — stakeholders
 * com notification center ativo veem na navbar.
 *
 * `ShouldQueue` evita que rate limit do SMTP (ex: Mailtrap free) ou
 * indisponibilidade do mailer trave a request HTTP de reopen.
 */
class DrePeriodReopenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly DrePeriodClosing $closing,
        public readonly User $reopenedBy,
        public readonly string $reason,
        public readonly ReopenReport $report,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = sprintf(
            '[DRE] Fechamento de %s foi reaberto',
            $this->closing->closed_up_to_date?->format('d/m/Y') ?? '?'
        );

        $msg = (new MailMessage())
            ->subject($subject)
            ->greeting('Olá!')
            ->line(sprintf(
                '%s reabriu o fechamento da DRE até %s.',
                $this->reopenedBy->name,
                $this->closing->closed_up_to_date?->format('d/m/Y') ?? '?',
            ))
            ->line('**Justificativa:** '.$this->reason);

        if ($this->report->hasDiffs()) {
            $msg->line('**Diferenças detectadas desde o fechamento** (até 20 primeiras):');

            foreach (array_slice($this->report->diffs, 0, 20) as $diff) {
                $scope = $diff['scope'];
                $scopeLabel = $diff['scope_id'] ? "{$scope} #{$diff['scope_id']}" : $scope;
                $msg->line(sprintf(
                    '- %s / %s / %s: snapshot %s → atual %s (Δ %s)',
                    $scopeLabel,
                    $diff['year_month'],
                    $diff['line_code'] ?? '?',
                    number_format((float) $diff['snapshot_actual'], 2, ',', '.'),
                    number_format((float) $diff['current_actual'], 2, ',', '.'),
                    number_format((float) $diff['delta'], 2, ',', '.'),
                ));
            }

            if (count($this->report->diffs) > 20) {
                $remaining = count($this->report->diffs) - 20;
                $msg->line("_(+{$remaining} diferenças — ver detalhe na tela)_");
            }
        } else {
            $msg->line('Nenhuma diferença detectada entre o snapshot e a matriz atual.');
        }

        return $msg->action('Abrir DRE', url('/dre/periods'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'dre.period.reopened',
            'closing_id' => $this->closing->id,
            'closed_up_to_date' => $this->closing->closed_up_to_date?->format('Y-m-d'),
            'reopened_by' => $this->reopenedBy->name,
            'reason' => $this->reason,
            'diffs_count' => count($this->report->diffs),
        ];
    }
}
