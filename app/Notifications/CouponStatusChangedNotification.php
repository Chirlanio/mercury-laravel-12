<?php

namespace App\Notifications;

use App\Enums\CouponStatus;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação quando um cupom muda de status. Disparada pelo
 * NotifyCouponStakeholders em resposta ao evento CouponStatusChanged.
 *
 * Canais por transição:
 *  - → requested  : database + mail (e-commerce precisa atuar)
 *  - → active     : database + mail (criador confirma publicação)
 *  - demais (issued/cancelled/expired) : apenas database
 *    (mail só nas duas transições críticas pra evitar caixa postal cheia)
 *
 * Quando $mailOnly = true: só dispara o canal mail (usado pelo listener
 * pra mandar cópia ao criador em → requested sem gerar bell duplicado).
 */
class CouponStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Coupon $coupon,
        public CouponStatus $fromStatus,
        public CouponStatus $toStatus,
        public ?User $actor,
        public ?string $note,
        public bool $mailOnly = false,
    ) {}

    public function via(object $notifiable): array
    {
        if ($this->mailOnly) {
            return $this->shouldSendMail($notifiable) ? ['mail'] : [];
        }

        $channels = ['database'];

        if ($this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'coupon_status_changed',
            'coupon_id' => $this->coupon->id,
            'coupon_type' => $this->coupon->type?->value,
            'coupon_type_label' => $this->coupon->type?->label(),
            'beneficiary_name' => $this->coupon->beneficiary_name,
            'store_code' => $this->coupon->store_code,
            'coupon_site' => $this->coupon->coupon_site,
            'suggested_coupon' => $this->coupon->suggested_coupon,
            'from_status' => $this->fromStatus->value,
            'from_status_label' => $this->fromStatus->label(),
            'to_status' => $this->toStatus->value,
            'to_status_label' => $this->toStatus->label(),
            'actor_name' => $this->actor?->name,
            'note' => $this->note,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->toStatus) {
            CouponStatus::REQUESTED => $this->mailForRequested($notifiable),
            CouponStatus::ACTIVE => $this->mailForActive($notifiable),
            default => (new MailMessage)->subject('Atualização de cupom'),
        };
    }

    protected function mailForRequested(object $notifiable): MailMessage
    {
        $coupon = $this->coupon;
        $beneficiary = $coupon->beneficiary_name ?: '—';
        $type = $coupon->type?->label() ?? '—';
        $store = $coupon->store_code ?: '—';
        $suggested = $coupon->suggested_coupon ?: '—';

        $message = (new MailMessage)
            ->subject("[Cupons] Nova solicitação — {$beneficiary}")
            ->greeting("Olá, {$notifiable->name}")
            ->line('Uma nova solicitação de cupom foi cadastrada e aguarda emissão.')
            ->line("**Beneficiário:** {$beneficiary}")
            ->line("**Tipo:** {$type}")
            ->line("**Loja:** {$store}")
            ->line("**Código sugerido:** {$suggested}");

        if ($this->actor) {
            $message->line("**Solicitante:** {$this->actor->name}");
        }

        return $message
            ->action('Abrir solicitação', url("/coupons/{$coupon->id}"))
            ->line('Acesse o módulo de Cupons para emitir o código na plataforma.');
    }

    protected function mailForActive(object $notifiable): MailMessage
    {
        $coupon = $this->coupon;
        $beneficiary = $coupon->beneficiary_name ?: '—';
        $code = $coupon->coupon_site ?: $coupon->suggested_coupon ?: '—';

        $message = (new MailMessage)
            ->subject("[Cupons] Cupom ativado — {$code}")
            ->greeting("Olá, {$notifiable->name}")
            ->line('O cupom solicitado foi ativado e já está publicado na plataforma.')
            ->line("**Beneficiário:** {$beneficiary}")
            ->line("**Código:** {$code}");

        if ($coupon->valid_from) {
            $message->line('**Válido a partir de:** '.$coupon->valid_from->format('d/m/Y'));
        }
        if ($coupon->valid_until) {
            $message->line('**Válido até:** '.$coupon->valid_until->format('d/m/Y'));
        }
        if ($this->note) {
            $message->line("**Observação:** {$this->note}");
        }

        return $message
            ->action('Ver cupom', url("/coupons/{$coupon->id}"))
            ->line('Boas vendas!');
    }

    protected function shouldSendMail(object $notifiable): bool
    {
        if (! in_array($this->toStatus, [CouponStatus::REQUESTED, CouponStatus::ACTIVE], true)) {
            return false;
        }

        return ! empty($notifiable->email ?? null);
    }
}
