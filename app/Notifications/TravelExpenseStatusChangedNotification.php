<?php

namespace App\Notifications;

use App\Enums\AccountabilityStatus;
use App\Enums\TravelExpenseStatus;
use App\Models\TravelExpense;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação quando uma verba de viagem muda de status. Disparada pelo
 * NotifyTravelExpenseStakeholders em resposta ao evento TravelExpenseStatusChanged.
 *
 * Cobre as duas state machines (kind = 'expense' | 'accountability').
 *
 * Canais por transição:
 *  - submitted (expense ou accountability): database + mail (Financeiro
 *    precisa atuar)
 *  - approved (expense): database + mail (criador confirma adiantamento)
 *  - rejected (expense ou accountability): database + mail (criador
 *    precisa corrigir)
 *  - cancelled / finalized: apenas database (informativo)
 *  - draft (volta): apenas database (devolução pra correção)
 */
class TravelExpenseStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TravelExpense $travelExpense,
        public TravelExpenseStatus|AccountabilityStatus $fromStatus,
        public TravelExpenseStatus|AccountabilityStatus $toStatus,
        public ?User $actor,
        public ?string $note,
        public string $kind = 'expense',
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $te = $this->travelExpense;

        return [
            'type' => 'travel_expense_status_changed',
            'kind' => $this->kind,
            'travel_expense_id' => $te->id,
            'travel_expense_ulid' => $te->ulid,
            'employee_name' => $te->employee?->name,
            'store_code' => $te->store_code,
            'origin' => $te->origin,
            'destination' => $te->destination,
            'value' => (float) $te->value,
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
        if ($this->kind === 'accountability') {
            return match ($this->toStatus) {
                AccountabilityStatus::SUBMITTED => $this->mailForAccountabilitySubmitted($notifiable),
                AccountabilityStatus::REJECTED => $this->mailForAccountabilityRejected($notifiable),
                default => $this->genericMail($notifiable),
            };
        }

        return match ($this->toStatus) {
            TravelExpenseStatus::SUBMITTED => $this->mailForSubmitted($notifiable),
            TravelExpenseStatus::APPROVED => $this->mailForApproved($notifiable),
            TravelExpenseStatus::REJECTED => $this->mailForRejected($notifiable),
            default => $this->genericMail($notifiable),
        };
    }

    // ------------------------------------------------------------------
    // Mail templates — solicitação
    // ------------------------------------------------------------------

    protected function mailForSubmitted(object $notifiable): MailMessage
    {
        $te = $this->travelExpense;

        $message = (new MailMessage)
            ->subject("[Verbas de Viagem] Nova solicitação — {$te->employee?->name}")
            ->greeting("Olá, {$notifiable->name}")
            ->line('Uma nova solicitação de verba de viagem foi enviada e aguarda aprovação.')
            ->line("**Beneficiado:** {$te->employee?->name}")
            ->line("**Trecho:** {$te->origin} → {$te->destination}")
            ->line('**Período:** '.$te->initial_date?->format('d/m/Y').' a '.$te->end_date?->format('d/m/Y'))
            ->line("**Valor:** ".$this->formatCurrency($te->value));

        if ($this->actor) {
            $message->line("**Solicitante:** {$this->actor->name}");
        }
        if ($this->note) {
            $message->line("**Observação:** {$this->note}");
        }

        return $message
            ->action('Analisar solicitação', url("/travel-expenses?status=submitted"))
            ->line('Acesse o módulo de Verbas de Viagem para aprovar ou rejeitar.');
    }

    protected function mailForApproved(object $notifiable): MailMessage
    {
        $te = $this->travelExpense;

        $message = (new MailMessage)
            ->subject("[Verbas de Viagem] Solicitação aprovada — {$te->origin} → {$te->destination}")
            ->greeting("Olá, {$notifiable->name}")
            ->line('Sua solicitação de verba de viagem foi **aprovada**.')
            ->line("**Trecho:** {$te->origin} → {$te->destination}")
            ->line('**Período:** '.$te->initial_date?->format('d/m/Y').' a '.$te->end_date?->format('d/m/Y'))
            ->line("**Valor liberado:** ".$this->formatCurrency($te->value));

        if ($this->actor) {
            $message->line("**Aprovado por:** {$this->actor->name}");
        }
        if ($this->note) {
            $message->line("**Observação:** {$this->note}");
        }

        return $message
            ->action('Ver detalhes', url("/travel-expenses"))
            ->line('Após a viagem, lembre-se de lançar a prestação de contas com os comprovantes.');
    }

    protected function mailForRejected(object $notifiable): MailMessage
    {
        $te = $this->travelExpense;

        $message = (new MailMessage)
            ->subject("[Verbas de Viagem] Solicitação rejeitada — {$te->origin} → {$te->destination}")
            ->greeting("Olá, {$notifiable->name}")
            ->line('Sua solicitação de verba de viagem foi **rejeitada**.')
            ->line("**Trecho:** {$te->origin} → {$te->destination}")
            ->line('**Período:** '.$te->initial_date?->format('d/m/Y').' a '.$te->end_date?->format('d/m/Y'));

        if ($this->note) {
            $message->line("**Motivo:** {$this->note}");
        }
        if ($this->actor) {
            $message->line("**Rejeitada por:** {$this->actor->name}");
        }

        return $message
            ->action('Revisar solicitação', url('/travel-expenses'))
            ->line('Você pode editar e reenviar a solicitação a partir do módulo.');
    }

    // ------------------------------------------------------------------
    // Mail templates — prestação de contas
    // ------------------------------------------------------------------

    protected function mailForAccountabilitySubmitted(object $notifiable): MailMessage
    {
        $te = $this->travelExpense;

        return (new MailMessage)
            ->subject("[Verbas de Viagem] Prestação enviada — {$te->employee?->name}")
            ->greeting("Olá, {$notifiable->name}")
            ->line('Uma prestação de contas foi enviada e aguarda aprovação.')
            ->line("**Beneficiado:** {$te->employee?->name}")
            ->line("**Trecho:** {$te->origin} → {$te->destination}")
            ->line("**Verba aprovada:** ".$this->formatCurrency($te->value))
            ->line("**Total prestado:** ".$this->formatCurrency($te->accounted_value))
            ->action('Analisar prestação', url('/travel-expenses?accountability_status=submitted'))
            ->line('Acesse para aprovar ou devolver para correção.');
    }

    protected function mailForAccountabilityRejected(object $notifiable): MailMessage
    {
        $te = $this->travelExpense;

        $message = (new MailMessage)
            ->subject("[Verbas de Viagem] Prestação devolvida — {$te->origin} → {$te->destination}")
            ->greeting("Olá, {$notifiable->name}")
            ->line('Sua prestação de contas foi devolvida para correção.');

        if ($this->note) {
            $message->line("**Motivo:** {$this->note}");
        }

        return $message
            ->action('Corrigir prestação', url('/travel-expenses'))
            ->line('Faça os ajustes solicitados e reenvie a prestação.');
    }

    protected function genericMail(object $notifiable): MailMessage
    {
        $te = $this->travelExpense;

        return (new MailMessage)
            ->subject("[Verbas de Viagem] Atualização — {$te->origin} → {$te->destination}")
            ->greeting("Olá, {$notifiable->name}")
            ->line("Houve uma atualização na verba de viagem do colaborador {$te->employee?->name}.")
            ->line("**De:** {$this->fromStatus->label()}")
            ->line("**Para:** {$this->toStatus->label()}")
            ->action('Ver detalhes', url('/travel-expenses'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function shouldSendMail(object $notifiable): bool
    {
        if (empty($notifiable->email ?? null)) {
            return false;
        }

        // Estados que enviam mail (além de database)
        $mailableExpenseStatuses = [
            TravelExpenseStatus::SUBMITTED,
            TravelExpenseStatus::APPROVED,
            TravelExpenseStatus::REJECTED,
        ];
        $mailableAccountabilityStatuses = [
            AccountabilityStatus::SUBMITTED,
            AccountabilityStatus::REJECTED,
        ];

        if ($this->kind === 'accountability') {
            return in_array($this->toStatus, $mailableAccountabilityStatuses, true);
        }

        return in_array($this->toStatus, $mailableExpenseStatuses, true);
    }

    protected function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
