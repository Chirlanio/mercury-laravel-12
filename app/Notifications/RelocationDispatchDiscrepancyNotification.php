<?php

namespace App\Notifications;

use App\Models\Relocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta de divergências detectadas no despacho — qty separada não bate
 * com a NF emitida no CIGAM (faltando/sobrando/qty diferente). Disparado
 * pelo listener NotifyDispatchDiscrepancies.
 *
 * Database + mail (mais urgente que RelocationStatusChangedNotification,
 * que é só database). Justificativa: divergência implica investigação
 * imediata (problema na separação física, NF errada, ou nota fiscal de
 * outro pedido emitida no lugar). Time de planejamento/logística precisa
 * ver mesmo se não estiver no sistema.
 */
class RelocationDispatchDiscrepancyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Relocation $relocation) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $diag = $this->relocation->dispatch_discrepancies_json ?? [];

        return [
            'type' => 'relocation_dispatch_discrepancy',
            'relocation_id' => $this->relocation->id,
            'relocation_ulid' => $this->relocation->ulid,
            'title' => $this->relocation->title,
            'origin_store_code' => $this->relocation->originStore?->code,
            'destination_store_code' => $this->relocation->destinationStore?->code,
            'invoice_number' => $this->relocation->invoice_number,
            'invoice_date' => $this->relocation->invoice_date?->format('Y-m-d'),
            'missing_count' => count($diag['missing'] ?? []),
            'extra_count' => count($diag['extra'] ?? []),
            'divergent_count' => count($diag['divergent'] ?? []),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->relocation;
        $diag = $r->dispatch_discrepancies_json ?? [];
        $missingCount = count($diag['missing'] ?? []);
        $extraCount = count($diag['extra'] ?? []);
        $divergentCount = count($diag['divergent'] ?? []);

        $url = url("/relocations?status=in_transit");

        $msg = (new MailMessage)
            ->error()
            ->subject('Divergência no despacho — Remanejo '.($r->title ?: '#'.$r->id))
            ->greeting('Atenção: divergência detectada')
            ->line(sprintf(
                'O remanejo de %s para %s foi confirmado com a NF %s, mas os itens não batem com o que foi separado.',
                $r->originStore?->code ?? '—',
                $r->destinationStore?->code ?? '—',
                $r->invoice_number ?? '—',
            ));

        if ($missingCount > 0) {
            $msg->line("- {$missingCount} item(ns) separado(s) que NÃO aparece(m) na NF (faltando).");
        }
        if ($extraCount > 0) {
            $msg->line("- {$extraCount} item(ns) na NF que NÃO foi(ram) solicitado(s) no remanejo (sobrando).");
        }
        if ($divergentCount > 0) {
            $msg->line("- {$divergentCount} item(ns) com quantidade diferente entre separação e NF.");
        }

        return $msg
            ->action('Ver detalhes do remanejo', $url)
            ->line('Verifique a separação física, a NF emitida no CIGAM ou abra um chamado pra logística.');
    }
}
