<?php

namespace App\Notifications\Helpdesk;

use App\Models\HdTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
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
            ->subject("Novo chamado #{$this->ticket->id}: {$this->ticket->title}")
            ->greeting("Olá, {$notifiable->name}")
            ->line("Um novo chamado foi criado no departamento **{$this->ticket->department?->name}**.")
            ->line("**Solicitante:** {$this->ticket->requester?->name}")
            ->line("**Prioridade:** ".(HdTicket::PRIORITY_LABELS[$this->ticket->priority] ?? '-'))
            ->line("**Descrição:** {$this->ticket->description}")
            ->action('Ver Chamado', url("/helpdesk/{$this->ticket->id}"))
            ->line('Atenda o quanto antes para respeitar o SLA.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'ticket_created',
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'department_id' => $this->ticket->department_id,
            'department_name' => $this->ticket->department?->name,
            'requester_name' => $this->ticket->requester?->name,
            'priority' => $this->ticket->priority,
            'priority_label' => HdTicket::PRIORITY_LABELS[$this->ticket->priority] ?? null,
        ];
    }
}
