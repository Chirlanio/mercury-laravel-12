<?php

namespace App\Notifications;

use App\Enums\ConsignmentStatus;
use App\Models\Consignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notificação quando uma consignação muda de status. Disparada pelo
 * NotifyConsignmentStakeholders em resposta ao evento
 * ConsignmentStatusChanged.
 *
 * Canal único: database (sino). Por decisão de escopo da Fase 4,
 * não envia e-mail — operação diária da loja. E-mail pode ser
 * adicionado em fase futura com opt-in por perfil.
 */
class ConsignmentStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Consignment $consignment,
        public ConsignmentStatus $fromStatus,
        public ConsignmentStatus $toStatus,
        public ?User $actor,
        public ?string $note,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'consignment_status_changed',
            'consignment_id' => $this->consignment->id,
            'consignment_uuid' => $this->consignment->uuid,
            'consignment_type' => $this->consignment->type?->value,
            'consignment_type_label' => $this->consignment->type?->label(),
            'recipient_name' => $this->consignment->recipient_name,
            'outbound_invoice_number' => $this->consignment->outbound_invoice_number,
            'store_id' => $this->consignment->store_id,
            'store_code' => $this->consignment->outbound_store_code,
            'from_status' => $this->fromStatus->value,
            'from_status_label' => $this->fromStatus->label(),
            'to_status' => $this->toStatus->value,
            'to_status_label' => $this->toStatus->label(),
            'to_status_color' => $this->toStatus->color(),
            'expected_return_date' => $this->consignment->expected_return_date?->format('Y-m-d'),
            'actor_name' => $this->actor?->name,
            'note' => $this->note,
            'title' => $this->buildTitle(),
            'url' => route('consignments.index').'?id='.$this->consignment->id,
        ];
    }

    protected function buildTitle(): string
    {
        return match ($this->toStatus) {
            ConsignmentStatus::PENDING => 'Consignação emitida',
            ConsignmentStatus::PARTIALLY_RETURNED => 'Retorno parcial registrado',
            ConsignmentStatus::OVERDUE => 'Consignação em atraso',
            ConsignmentStatus::COMPLETED => 'Consignação finalizada',
            ConsignmentStatus::CANCELLED => 'Consignação cancelada',
            default => 'Atualização de consignação',
        };
    }
}
