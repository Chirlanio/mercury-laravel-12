<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta consolidado de consumo de orçamento. Um único envio por
 * usuário por execução do command, com todos os budgets do ano em
 * warning/exceeded agrupados.
 *
 * Canais: database (sino do frontend) + mail (cópia escrita para o
 * gestor). Usuário pode desativar o mail via preferências se adicionado
 * no futuro.
 */
class BudgetAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array  $scan  Payload retornado por BudgetAlertService::scanAlerts()
     */
    public function __construct(public array $scan) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $year = $this->scan['year'];
        $summary = $this->scan['summary'];
        $alerts = $this->scan['alerts'];

        $mail = (new MailMessage)
            ->subject("[Orçamentos] Alerta de consumo — {$year}")
            ->greeting("Olá, {$notifiable->name}")
            ->line(sprintf(
                '**%d orçamento(s)** do ano %d apresentam consumo elevado: %d com alerta (≥70%%) e %d com excesso (≥100%%).',
                $summary['warning_count'] + $summary['exceeded_count'],
                $year,
                $summary['warning_count'],
                $summary['exceeded_count']
            ));

        foreach ($alerts as $alert) {
            $icon = $alert['status'] === 'exceeded' ? '🔴' : '🟡';
            $mail->line(sprintf(
                "\n%s **%s v%s** — %.1f%% consumido",
                $icon,
                $alert['scope_label'],
                $alert['version_label'],
                $alert['total_pct']
            ));

            if (! empty($alert['exceeded_ccs'])) {
                $mail->line('CCs excedidos:');
                foreach (array_slice($alert['exceeded_ccs'], 0, 5) as $cc) {
                    $mail->line(sprintf(
                        '  • %s · %s — %.1f%%',
                        $cc['code'],
                        $cc['name'],
                        $cc['utilization_pct']
                    ));
                }
            }
            if (! empty($alert['warning_ccs'])) {
                $mail->line('CCs em alerta:');
                foreach (array_slice($alert['warning_ccs'], 0, 5) as $cc) {
                    $mail->line(sprintf(
                        '  • %s · %s — %.1f%%',
                        $cc['code'],
                        $cc['name'],
                        $cc['utilization_pct']
                    ));
                }
            }
        }

        return $mail
            ->action('Abrir orçamentos', route('budgets.index'))
            ->line('Acesse o dashboard do orçamento para ver detalhes e tomar decisão.');
    }

    public function toArray(object $notifiable): array
    {
        $year = $this->scan['year'];
        $summary = $this->scan['summary'];
        $alerts = $this->scan['alerts'];

        $totalWarning = 0;
        $totalExceeded = 0;
        $affectedScopes = [];
        foreach ($alerts as $alert) {
            $totalWarning += count($alert['warning_ccs']) + $alert['warning_items'];
            $totalExceeded += count($alert['exceeded_ccs']) + $alert['exceeded_items'];
            $affectedScopes[] = $alert['scope_label'];
        }

        return [
            'type' => 'budget_alert',
            'title' => "Alerta de consumo de orçamento — {$year}",
            'message' => sprintf(
                '%d orçamento(s) com consumo elevado: %d em alerta, %d excedidos',
                count($alerts),
                $summary['warning_count'],
                $summary['exceeded_count']
            ),
            'year' => $year,
            'summary' => $summary,
            'affected_scopes' => array_values(array_unique($affectedScopes)),
            'warning_total' => $totalWarning,
            'exceeded_total' => $totalExceeded,
            'url' => route('budgets.index'),
        ];
    }
}
