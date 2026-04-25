<?php

namespace App\Listeners;

use App\Enums\AccountabilityStatus;
use App\Enums\TravelExpenseStatus;
use App\Events\TravelExpenseStatusChanged;
use App\Models\HdDepartment;
use App\Services\HelpdeskService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Hook opcional: quando uma verba ou prestação é REJEITADA, abre
 * automaticamente um ticket no departamento "Financeiro" do Helpdesk
 * para formalizar a discussão e dar visibilidade ao processo.
 *
 * Idempotente: se a verba já tem helpdesk_ticket_id, não cria outro.
 *
 * Fail-safe:
 *  - Helpdesk não instalado → skip silencioso
 *  - Departamento "Financeiro" inativo/inexistente → skip com info log
 *  - Qualquer outro erro → loga warning e segue (não quebra fluxo de
 *    transição, que já foi commitado)
 *
 * Para desativar o hook em um tenant específico, basta renomear ou
 * desativar (is_active=false) o departamento Financeiro no Helpdesk.
 *
 * Auto-discovery do Laravel 12 registra esta classe — NÃO chamar
 * Event::listen manualmente em providers (causaria duplicação).
 */
class OpenHelpdeskTicketForTravelExpense
{
    public function __construct(protected HelpdeskService $helpdesk) {}

    public function handle(TravelExpenseStatusChanged $event): void
    {
        $isExpenseRejected = $event->kind === 'expense'
            && $event->toStatus === TravelExpenseStatus::REJECTED;

        $isAccountabilityRejected = $event->kind === 'accountability'
            && $event->toStatus === AccountabilityStatus::REJECTED;

        if (! $isExpenseRejected && ! $isAccountabilityRejected) {
            return;
        }

        $te = $event->travelExpense;

        if ($te->helpdesk_ticket_id) {
            return; // Idempotente
        }

        if (! Schema::hasTable('hd_departments') || ! Schema::hasTable('hd_tickets')) {
            return; // Helpdesk não instalado
        }

        try {
            $department = HdDepartment::query()
                ->active()
                ->whereRaw('LOWER(name) = ?', ['financeiro'])
                ->first();

            if (! $department) {
                Log::info('Travel-expense Helpdesk hook skipped: no active Financeiro department', [
                    'travel_expense_id' => $te->id,
                ]);
                return;
            }

            $kindLabel = $isAccountabilityRejected ? 'Prestação de contas' : 'Solicitação';
            $valor = number_format((float) $te->value, 2, ',', '.');
            $accounted = $isAccountabilityRejected
                ? number_format((float) $te->items->sum('value'), 2, ',', '.')
                : null;

            $title = sprintf(
                'Verba de viagem rejeitada — %s → %s (R$ %s)',
                $te->origin,
                $te->destination,
                $valor
            );

            $description = sprintf(
                "%s rejeitada — abertura automática para acompanhamento.\n\n"
                ."• Verba: #%d\n"
                ."• Beneficiado: %s\n"
                ."• Loja: %s\n"
                ."• Trecho: %s → %s\n"
                ."• Período: %s a %s (%d dias)\n"
                ."• Valor da verba: R\$ %s\n",
                $kindLabel,
                $te->id,
                $te->employee?->name ?? '—',
                $te->store_code ?? '—',
                $te->origin,
                $te->destination,
                $te->initial_date?->format('d/m/Y') ?? '—',
                $te->end_date?->format('d/m/Y') ?? '—',
                $te->days_count,
                $valor
            );

            if ($accounted !== null) {
                $description .= "• Total prestado: R\$ {$accounted}\n";
            }

            if ($event->note) {
                $description .= "• Motivo: {$event->note}\n";
            }

            $description .= sprintf(
                "• Rejeitada por: %s\n\nConsulte os detalhes em /travel-expenses (filtrar por #%d).",
                $event->actor?->name ?? '—',
                $te->id
            );

            $ticket = $this->helpdesk->createTicket(
                [
                    'department_id' => $department->id,
                    'title' => $title,
                    'description' => $description,
                    'priority' => 2, // Normal
                    'source' => 'system',
                ],
                $event->actor?->id
            );

            $te->update(['helpdesk_ticket_id' => $ticket->id]);
        } catch (\Throwable $e) {
            Log::warning('Failed to open Helpdesk ticket for travel-expense', [
                'travel_expense_id' => $te->id,
                'kind' => $event->kind,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
