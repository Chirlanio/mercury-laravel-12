<?php

namespace App\Notifications\Helpdesk;

use App\Models\HdTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
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
            ->subject("Chamado #{$this->ticket->id} atribuído a você")
            ->greeting("Olá, {$notifiable->name}")
            ->line("O chamado **#{$this->ticket->id} - {$this->ticket->title}** foi atribuído a você.")
            ->line("**Departamento:** {$this->ticket->department?->name}")
            ->line("**Prioridade:** ".(HdTicket::PRIORITY_LABELS[$this->ticket->priority] ?? '-'))
            ->line("**SLA:** até ".($this->ticket->sla_due_at?->format('d/m/Y H:i') ?? '-'))
            ->action('Abrir Chamado', url("/helpdesk/{$this->ticket->id}"));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'department_name' => $this->ticket->department?->name,
            'priority' => $this->ticket->priority,
            'sla_due_at' => $this->ticket->sla_due_at?->toIso8601String(),
        ];
    }
}
