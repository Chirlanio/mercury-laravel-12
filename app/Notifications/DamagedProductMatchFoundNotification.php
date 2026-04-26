<?php

namespace App\Notifications;

use App\Models\DamagedProductMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica gerentes das duas lojas envolvidas quando a engine cria
 * um match. Database (sino) sempre; mail apenas para gerentes da
 * loja sugerida como destino (precisam aceitar ou rejeitar).
 */
class DamagedProductMatchFoundNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DamagedProductMatch $match,
        public bool $sendMail = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sendMail ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $partner = $this->match->productA;
        $other = $this->match->productB;

        return [
            'type' => 'damaged_product_match_found',
            'match_id' => $this->match->id,
            'match_type' => $this->match->match_type->value,
            'match_type_label' => $this->match->match_type->label(),
            'match_score' => (float) $this->match->match_score,
            'product_reference' => $partner?->product_reference,
            'origin_store' => $this->match->suggestedOriginStore?->code,
            'destination_store' => $this->match->suggestedDestinationStore?->code,
            'product_a_id' => $this->match->product_a_id,
            'product_b_id' => $this->match->product_b_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $partner = $this->match->productA;
        $type = $this->match->match_type->label();
        $origin = $this->match->suggestedOriginStore?->code ?? '—';
        $destination = $this->match->suggestedDestinationStore?->code ?? '—';

        return (new MailMessage)
            ->subject('Mercury — Novo match de produto avariado encontrado')
            ->greeting('Olá!')
            ->line("A engine de matching encontrou um {$type} viável envolvendo a referência **{$partner?->product_reference}**.")
            ->line("Direção sugerida: **{$origin}** → **{$destination}**.")
            ->action('Ver match', url('/damaged-products'))
            ->line('Acesse o módulo Produtos Avariados para aceitar ou rejeitar.');
    }
}
