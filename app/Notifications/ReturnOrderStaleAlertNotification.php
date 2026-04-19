<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta diário consolidado de devoluções paradas em `awaiting_product`
 * há mais de N dias (default 7). Enviado pelo ReturnOrdersStaleAlertCommand
 * para usuários com PROCESS_RETURNS (quem tem que acionar o cliente).
 *
 * Mail + database. O mail é consolidado — um por usuário listando todas
 * as devoluções atrasadas, não uma notificação por item.
 */
class ReturnOrderStaleAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array{id:int, invoice_number:string, store_code:string, customer_name:string, amount_items:float, type_label:string, created_at:string, days_pending:int}> $staleReturns
     * @param int $daysThreshold Dias de tolerância usados na query
     */
    public function __construct(
        public array $staleReturns,
        public int $daysThreshold,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->staleReturns);
        $msg = (new MailMessage())
            ->subject("[Devoluções] {$count} solicitação(ões) aguardando produto há mais de {$this->daysThreshold} dias")
            ->greeting("Olá, {$notifiable->name}")
            ->line(
                "Existem {$count} devolução(ões) em que o cliente deveria ter postado o produto há mais de {$this->daysThreshold} dias. "
                .'Recomendamos entrar em contato com o cliente para confirmar o envio ou cancelar a solicitação:'
            );

        foreach ($this->staleReturns as $r) {
            $msg->line(
                sprintf(
                    '• NF %s — %s — %s — R$ %s — %d dias aguardando',
                    $r['invoice_number'],
                    $r['type_label'],
                    $r['customer_name'],
                    number_format($r['amount_items'], 2, ',', '.'),
                    $r['days_pending']
                )
            );
        }

        return $msg
            ->action('Abrir lista de devoluções', url('/returns?status=awaiting_product'))
            ->line('Obrigado.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'return_stale_alert',
            'count' => count($this->staleReturns),
            'days_threshold' => $this->daysThreshold,
            'returns' => $this->staleReturns,
        ];
    }
}
