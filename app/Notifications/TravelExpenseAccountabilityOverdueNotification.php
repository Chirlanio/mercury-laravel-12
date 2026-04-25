<?php

namespace App\Notifications;

use App\Models\TravelExpense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que uma verba aprovada está com prestação de contas atrasada.
 * Disparado pelo command travel-expenses:accountability-overdue (default
 * 3 dias após end_date).
 *
 * Canais: database + mail (esta é a fila de cobrança — precisa visibilidade).
 */
class TravelExpenseAccountabilityOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TravelExpense $travelExpense,
        public int $daysThreshold = 3,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (! empty($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $te = $this->travelExpense;

        return [
            'type' => 'travel_expense_accountability_overdue',
            'travel_expense_id' => $te->id,
            'travel_expense_ulid' => $te->ulid,
            'employee_name' => $te->employee?->name,
            'origin' => $te->origin,
            'destination' => $te->destination,
            'end_date' => $te->end_date?->format('Y-m-d'),
            'days_overdue' => abs($te->days_since_end),
            'value' => (float) $te->value,
            'days_threshold' => $this->daysThreshold,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $te = $this->travelExpense;
        $daysOverdue = abs($te->days_since_end);

        return (new MailMessage)
            ->subject("[Verbas de Viagem] Prestação atrasada — {$te->employee?->name}")
            ->greeting("Olá, {$notifiable->name}")
            ->line("A verba de viagem de **{$te->employee?->name}** está com a prestação de contas atrasada.")
            ->line("**Trecho:** {$te->origin} → {$te->destination}")
            ->line('**Retorno:** '.$te->end_date?->format('d/m/Y')." ({$daysOverdue} dias atrás)")
            ->line('**Valor da verba:** R$ '.number_format((float) $te->value, 2, ',', '.'))
            ->action('Lançar prestação de contas', url('/travel-expenses?accountability_status=pending&accountability_status=in_progress'))
            ->line('Por favor, regularize a prestação o quanto antes para não impactar o fechamento financeiro.');
    }
}
