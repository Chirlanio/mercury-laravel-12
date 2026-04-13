<?php

namespace App\Notifications\Helpdesk;

use App\Models\HdTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public HdTicket $ticket,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $oldLabel = HdTicket::STATUS_LABELS[$this->oldStatus] ?? $this->oldStatus;
        $newLabel = HdTicket::STATUS_LABELS[$this->newStatus] ?? $this->newStatus;

        return (new MailMessage)
            ->subject("Chamado #{$this->ticket->id} - Status atualizado")
            ->greeting("Olá, {$notifiable->name}")
            ->line("O status do chamado **#{$this->ticket->id} - {$this->ticket->title}** foi alterado.")
            ->line("**De:** {$oldLabel}")
            ->line("**Para:** {$newLabel}")
            ->action('Ver Chamado', url("/helpdesk/{$this->ticket->id}"));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'ticket_status_changed',
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'old_status_label' => HdTicket::STATUS_LABELS[$this->oldStatus] ?? $this->oldStatus,
            'new_status_label' => HdTicket::STATUS_LABELS[$this->newStatus] ?? $this->newStatus,
        ];
    }
}
