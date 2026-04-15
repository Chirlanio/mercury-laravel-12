<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notificação consolidada de ordens de compra atrasadas. Enviada
 * diariamente pelo command purchase-orders:late-alert para usuários com
 * APPROVE_PURCHASE_ORDERS, agrupando todas as ordens vencidas em uma
 * única mensagem.
 *
 * Canais: mail + database.
 */
class PurchaseOrderLateAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, \App\Models\PurchaseOrder>  $orders
     */
    public function __construct(public Collection $orders) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->orders->count();
        $mail = (new MailMessage)
            ->subject("{$count} ordem(ns) de compra atrasada(s)")
            ->greeting("Olá, {$notifiable->name}")
            ->line("Existem {$count} ordem(ns) de compra com previsão de entrega vencida que ainda não foram entregues.");

        foreach ($this->orders->take(10) as $order) {
            $supplier = $order->supplier?->nome_fantasia ?? '-';
            $forecast = $order->predict_date?->format('d/m/Y') ?? '-';
            $mail->line("• **#{$order->order_number}** — {$supplier} — Loja {$order->store_id} — Previsão: {$forecast}");
        }

        if ($count > 10) {
            $mail->line("...e mais " . ($count - 10) . " ordem(ns).");
        }

        return $mail
            ->action('Ver todas', url('/purchase-orders?include_terminal=0'))
            ->line('Esta é uma notificação automática do módulo de Ordens de Compra.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'purchase_order_late_alert',
            'count' => $this->orders->count(),
            'orders' => $this->orders->take(20)->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'supplier_name' => $o->supplier?->nome_fantasia,
                'store_id' => $o->store_id,
                'predict_date' => $o->predict_date?->toDateString(),
            ])->values()->all(),
        ];
    }
}
