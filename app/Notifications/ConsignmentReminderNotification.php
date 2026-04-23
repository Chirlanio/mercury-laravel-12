<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notificação consolidada — lembrete de consignações com prazo
 * próximo do vencimento OU consignações em atraso há dias demais.
 *
 * Canal único: database (sino). O `kind` diferencia os dois casos:
 *  - 'upcoming'  : ≤ N dias para vencer (consignments:remind-upcoming)
 *  - 'overdue'   : em atraso há ≥ N dias (consignments:overdue-alert)
 */
class ConsignmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $consignments  Payload resumido
     * @param  string  $kind  'upcoming' | 'overdue'
     */
    public function __construct(
        public array $consignments,
        public int $days,
        public string $kind = 'upcoming',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $total = count($this->consignments);
        $sumValue = array_sum(array_column($this->consignments, 'outbound_total_value'));

        return [
            'type' => 'consignment_reminder',
            'kind' => $this->kind,
            'total' => $total,
            'days' => $this->days,
            'total_value' => round($sumValue, 2),
            'consignments' => $this->consignments,
            'title' => $this->buildTitle($total),
            'url' => $this->kind === 'overdue'
                ? route('consignments.index').'?status=overdue'
                : route('consignments.index'),
        ];
    }

    protected function buildTitle(int $total): string
    {
        return match ($this->kind) {
            'overdue' => sprintf(
                '%d consignação(ões) em atraso há %d dia(s) ou mais',
                $total,
                $this->days,
            ),
            default => sprintf(
                '%d consignação(ões) vencem em até %d dia(s)',
                $total,
                $this->days,
            ),
        };
    }
}
