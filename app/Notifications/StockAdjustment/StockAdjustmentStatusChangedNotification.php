<?php

namespace App\Notifications\StockAdjustment;

use App\Models\StockAdjustment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockAdjustmentStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockAdjustment $adjustment,
        public string $oldStatus,
        public string $newStatus,
        public ?string $notes = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $oldLabel = StockAdjustment::STATUS_LABELS[$this->oldStatus] ?? $this->oldStatus;
        $newLabel = StockAdjustment::STATUS_LABELS[$this->newStatus] ?? $this->newStatus;

        return (new MailMessage)
            ->subject("Ajuste de estoque #{$this->adjustment->id} — {$newLabel}")
            ->greeting("Olá, {$notifiable->name}")
            ->line("O ajuste de estoque **#{$this->adjustment->id}** mudou de status.")
            ->line("**De:** {$oldLabel}")
            ->line("**Para:** {$newLabel}")
            ->line($this->notes ? "**Observação:** {$this->notes}" : '')
            ->action('Visualizar Ajuste', url("/stock-adjustments/{$this->adjustment->id}"));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'stock_adjustment_status_changed',
            'adjustment_id' => $this->adjustment->id,
            'store_id' => $this->adjustment->store_id,
            'store_name' => $this->adjustment->store?->name,
            'old_status' => $this->oldStatus,
            'old_status_label' => StockAdjustment::STATUS_LABELS[$this->oldStatus] ?? $this->oldStatus,
            'new_status' => $this->newStatus,
            'new_status_label' => StockAdjustment::STATUS_LABELS[$this->newStatus] ?? $this->newStatus,
            'notes' => $this->notes,
        ];
    }
}
