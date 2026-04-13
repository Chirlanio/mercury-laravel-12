<?php

namespace App\Notifications\Helpdesk;

use App\Models\HdTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public HdTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("SLA VENCIDO: Chamado #{$this->ticket->id}")
            ->greeting("Olá, {$notifiable->name}")
            ->line("O SLA do chamado **#{$this->ticket->id} - {$this->ticket->title}** foi ultrapassado.")
            ->line("**Vencido em:** ".($this->ticket->sla_due_at?->format('d/m/Y H:i') ?? '-'))
            ->line("**Departamento:** {$this->ticket->department?->name}")
            ->action('Atender Imediatamente', url("/helpdesk/{$this->ticket->id}"))
            ->level('error');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'sla_breached',
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'sla_due_at' => $this->ticket->sla_due_at?->toIso8601String(),
            'department_name' => $this->ticket->department?->name,
        ];
    }
}
