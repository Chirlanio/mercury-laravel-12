<?php

namespace App\Notifications\Helpdesk;

use App\Models\HdTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCommentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public HdTicket $ticket,
        public string $authorName,
        public bool $isInternal,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $prefix = $this->isInternal ? '[Nota Interna] ' : '';

        return (new MailMessage)
            ->subject("{$prefix}Chamado #{$this->ticket->id} - Novo comentário")
            ->greeting("Olá, {$notifiable->name}")
            ->line("{$this->authorName} comentou no chamado **#{$this->ticket->id} - {$this->ticket->title}**.")
            ->action('Ver Chamado', url("/helpdesk/{$this->ticket->id}"));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'ticket_commented',
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'author_name' => $this->authorName,
            'is_internal' => $this->isInternal,
        ];
    }
}
