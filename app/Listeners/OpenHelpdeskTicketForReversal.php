<?php

namespace App\Listeners;

use App\Enums\ReversalStatus;
use App\Events\ReversalStatusChanged;
use App\Models\HdDepartment;
use App\Services\HelpdeskService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Hook opcional: quando um estorno transita para pending_authorization,
 * abre automaticamente um ticket no departamento Financeiro do Helpdesk
 * para documentar/formalizar a aprovação.
 *
 * Idempotente: se o estorno já tem helpdesk_ticket_id, não cria outro.
 *
 * Fail-safe: se o módulo Helpdesk não está instalado, o departamento
 * "Financeiro" não existe ou qualquer outro erro, apenas loga e segue —
 * nunca quebra o fluxo de transição.
 *
 * Para desativar o hook em um tenant específico, basta renomear ou
 * desativar (is_active=false) o departamento Financeiro no Helpdesk.
 */
class OpenHelpdeskTicketForReversal
{
    public function __construct(protected HelpdeskService $helpdesk) {}

    public function handle(ReversalStatusChanged $event): void
    {
        if ($event->toStatus !== ReversalStatus::PENDING_AUTHORIZATION) {
            return;
        }

        $reversal = $event->reversal;

        if ($reversal->helpdesk_ticket_id) {
            return; // Idempotente
        }

        if (! Schema::hasTable('hd_departments') || ! Schema::hasTable('hd_tickets')) {
            // Modulo Helpdesk nao instalado neste tenant.
            return;
        }

        try {
            $department = HdDepartment::query()
                ->active()
                ->whereRaw('LOWER(name) = ?', ['financeiro'])
                ->first();

            if (! $department) {
                Log::info('Reversal Helpdesk hook skipped: no active Financeiro department', [
                    'reversal_id' => $reversal->id,
                ]);
                return;
            }

            $amount = number_format((float) $reversal->amount_reversal, 2, ',', '.');
            $saleDate = $reversal->movement_date?->format('d/m/Y') ?? '—';

            $title = sprintf(
                'Autorização de estorno — NF %s (loja %s) — R$ %s',
                $reversal->invoice_number,
                $reversal->store_code,
                $amount
            );

            $description = sprintf(
                "Solicitação de autorização de estorno gerada automaticamente.\n\n"
                ."• Estorno: #%d\n"
                ."• NF/Cupom: %s\n"
                ."• Loja: %s\n"
                ."• Data da venda: %s\n"
                ."• Cliente: %s\n"
                ."• Motivo: %s\n"
                ."• Valor a estornar: R\$ %s\n"
                ."• Solicitado por: %s\n\n"
                ."Consulte os detalhes completos em /reversals/%d",
                $reversal->id,
                $reversal->invoice_number,
                $reversal->store_code,
                $saleDate,
                $reversal->customer_name,
                $reversal->reason?->name ?? '—',
                $amount,
                $event->actor->name ?? '—',
                $reversal->id
            );

            $ticket = $this->helpdesk->createTicket(
                [
                    'department_id' => $department->id,
                    'title' => $title,
                    'description' => $description,
                    'priority' => 2, // Normal — SLA padrão do departamento
                    'source' => 'system',
                ],
                $event->actor->id
            );

            $reversal->update(['helpdesk_ticket_id' => $ticket->id]);
        } catch (\Throwable $e) {
            Log::warning('Failed to open Helpdesk ticket for reversal', [
                'reversal_id' => $reversal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
