<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta consolidado (database + mail) sobre remanejos atrasados —
 * disparado pelo command relocations:overdue-alert (daily 09:00).
 *
 * Recebe array de remanejos já filtrados pra evitar 1 notification por
 * remanejo (flood). 1 mail consolidado por destinatário com todos os
 * itens dele.
 */
class RelocationOverdueAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array{
     *   id:int, ulid:string, title:?string, days_overdue:int,
     *   origin_code:string, destination_code:string,
     *   priority_label:string, status_label:string
     * }> $relocations
     */
    public function __construct(public array $relocations) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->relocations);
        $msg = (new MailMessage)
            ->subject("Remanejos atrasados — {$count} pendência(s)")
            ->greeting('Olá!')
            ->line("Você tem {$count} remanejo(s) com prazo de atendimento vencido:");

        foreach ($this->relocations as $r) {
            $title = $r['title'] ?: "Remanejo #{$r['id']}";
            $msg->line(sprintf(
                '• %s (%s → %s) — %s · %d dia(s) atrasado',
                $title,
                $r['origin_code'],
                $r['destination_code'],
                $r['status_label'],
                $r['days_overdue']
            ));
        }

        return $msg
            ->action('Abrir remanejos', url('/relocations'))
            ->line('Atualize o status ou ajuste a deadline conforme necessário.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'relocations_overdue_alert',
            'count' => count($this->relocations),
            'relocations' => $this->relocations,
        ];
    }
}
