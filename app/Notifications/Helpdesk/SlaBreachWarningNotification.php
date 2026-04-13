<?php

namespace App\Notifications\Helpdesk;

use App\Models\HdTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public HdTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $remaining = round($this->ticket->sla_remaining_hours ?? 0, 1);

        return (new MailMessage)
            ->subject("ATENÇÃO: SLA do chamado #{$this->ticket->id} perto de expirar")
            ->greeting("Olá, {$notifiable->name}")
            ->line("O SLA do chamado **#{$this->ticket->id} - {$this->ticket->title}** está próximo de vencer.")
            ->line("**Tempo restante:** {$remaining}h")
            ->line("**Prazo:** ".($this->ticket->sla_due_at?->format('d/m/Y H:i') ?? '-'))
            ->action('Atender Agora', url("/helpdesk/{$this->ticket->id}"))
            ->level('warning');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'sla_warning',
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'sla_due_at' => $this->ticket->sla_due_at?->toIso8601String(),
            'remaining_hours' => $this->ticket->sla_remaining_hours,
        ];
    }
}
