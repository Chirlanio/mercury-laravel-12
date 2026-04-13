<?php

namespace App\Notifications\StockAdjustment;

use App\Models\StockAdjustment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockAdjustmentCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public StockAdjustment $adjustment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $store = $this->adjustment->store?->name ?? '';

        return (new MailMessage)
            ->subject("Novo ajuste de estoque #{$this->adjustment->id} — {$store}")
            ->greeting("Olá, {$notifiable->name}")
            ->line("Um novo ajuste de estoque foi solicitado pela loja **{$store}**.")
            ->line('**Itens:** '.$this->adjustment->items()->count())
            ->line($this->adjustment->observation
                ? "**Observação:** {$this->adjustment->observation}"
                : '')
            ->action('Visualizar Ajuste', url("/stock-adjustments/{$this->adjustment->id}"))
            ->line('Analise o quanto antes para manter o giro de estoque.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'stock_adjustment_created',
            'adjustment_id' => $this->adjustment->id,
            'store_id' => $this->adjustment->store_id,
            'store_name' => $this->adjustment->store?->name,
            'items_count' => $this->adjustment->items()->count(),
            'created_by' => $this->adjustment->createdBy?->name,
        ];
    }
}
