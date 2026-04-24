<?php

namespace App\Notifications;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta crítico: loja marcou item como 'vendido' mas o CIGAM não
 * tem movement_code=2 para o CPF do cliente nos 7 dias seguintes ao
 * retorno. Indica possível erro de processo (venda não registrada,
 * venda via outro canal, consignação esquecida, etc).
 *
 * Canais: database (sino do supervisor/gerente) + mail (loja via
 * email cadastrado). Justificativa do operador fica gravada no
 * histórico da consignação.
 */
class ConsignmentSaleUnconfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{reference: string, size_label: ?string, quantity: int}>  $items
     */
    public function __construct(
        public Consignment $consignment,
        public array $items,
        public string $justification,
        public ?string $registeredByName,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'consignment_sale_unconfirmed',
            'consignment_id' => $this->consignment->id,
            'consignment_uuid' => $this->consignment->uuid,
            'recipient_name' => $this->consignment->recipient_name,
            'recipient_document' => $this->consignment->recipient_document,
            'store_code' => $this->consignment->outbound_store_code,
            'outbound_invoice_number' => $this->consignment->outbound_invoice_number,
            'items' => $this->items,
            'justification' => $this->justification,
            'registered_by' => $this->registeredByName,
            'title' => 'Venda alegada sem confirmação no CIGAM',
            'url' => route('consignments.index').'?id='.$this->consignment->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $itemLines = collect($this->items)->map(
            fn ($it) => '• '.$it['reference'].' (Tam. '.($it['size_label'] ?? '—').') — '.$it['quantity'].' peça(s)'
        )->implode("\n");

        $mail = (new MailMessage)
            ->subject('[ATENÇÃO] Venda de consignação sem confirmação — '.$this->consignment->outbound_invoice_number)
            ->greeting('Equipe da loja '.$this->consignment->outbound_store_code)
            ->line(sprintf(
                'A consignação #%d para %s teve itens marcados como **vendidos**, mas o sistema CIGAM não registrou a venda correspondente nos últimos 7 dias.',
                $this->consignment->id,
                $this->consignment->recipient_name,
            ))
            ->line('Itens em questão:')
            ->line($itemLines)
            ->line('**Justificativa registrada pelo(a) operador(a)** ('.($this->registeredByName ?? '—').'):')
            ->line($this->justification)
            ->line('Por favor, verifique com a equipe se a venda foi:')
            ->line('• Registrada em outro sistema que ainda não sincronizou')
            ->line('• Feita para um CPF diferente do destinatário original')
            ->line('• Não realizada (erro de processo — item ainda deve voltar ou ser cobrado)')
            ->action('Abrir consignação', route('consignments.index').'?id='.$this->consignment->id);

        return $mail;
    }
}
