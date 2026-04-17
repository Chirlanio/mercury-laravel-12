<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta diário consolidado de estornos atrasados em aprovação. Enviado
 * por ReversalsStaleAlertCommand (daily 09:00) para usuários com
 * APPROVE_REVERSALS quando há estornos há mais de 3 dias aguardando
 * autorização.
 *
 * Mail + database. O mail consolidado evita flood — um email com a lista
 * completa em vez de um por reversal.
 */
class ReversalStaleAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array{id:int, invoice_number:string, store_code:string, customer_name:string, amount_reversal:float, created_at:string, days_pending:int}> $staleReversals
     */
    public function __construct(public array $staleReversals) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->staleReversals);
        $msg = (new MailMessage())
            ->subject("[Estornos] {$count} solicitação(ões) aguardando autorização há mais de 3 dias")
            ->greeting("Olá, {$notifiable->name}")
            ->line(
                "Existem {$count} estorno(s) aguardando sua autorização há mais de 3 dias. "
                .'Os itens abaixo estão prestes a impactar o atendimento ao cliente:'
            );

        foreach ($this->staleReversals as $r) {
            $msg->line(
                sprintf(
                    '• NF %s (loja %s) — %s — R$ %s — %d dias em espera',
                    $r['invoice_number'],
                    $r['store_code'],
                    $r['customer_name'],
                    number_format($r['amount_reversal'], 2, ',', '.'),
                    $r['days_pending']
                )
            );
        }

        return $msg
            ->action('Abrir lista de estornos', url('/reversals?status=pending_authorization'))
            ->line('Obrigado.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'reversal_stale_alert',
            'count' => count($this->staleReversals),
            'reversals' => $this->staleReversals,
        ];
    }
}
