<?php

namespace App\Listeners;

use App\Enums\RelocationStatus;
use App\Events\RelocationStatusChanged;
use App\Models\HdDepartment;
use App\Services\HelpdeskService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Hook opcional: quando um remanejo é REJEITADO ou CANCELADO pós-aprovação,
 * abre automaticamente um ticket no departamento Logística do Helpdesk
 * para documentar/investigar a causa.
 *
 * Idempotente: se o remanejo já tem helpdesk_ticket_id, não cria outro.
 *
 * Fail-safe: 3 níveis de proteção
 *   1. Schema check — módulo Helpdesk não instalado neste tenant
 *   2. Department check — depto "Logística" não existe ou desativado
 *   3. Try/catch global — qualquer erro inesperado loga e segue
 *
 * Para desativar o hook em um tenant específico, basta renomear ou
 * desativar (is_active=false) o departamento Logística no Helpdesk.
 *
 * Auto-discovered via type-hint do `handle(RelocationStatusChanged $e)`
 * — NÃO registrar via Event::listen manual.
 */
class OpenHelpdeskTicketForRelocation
{
    public function __construct(protected HelpdeskService $helpdesk) {}

    public function handle(RelocationStatusChanged $event): void
    {
        // Só age em rejeição ou cancelamento pós-aprovação (já passou pela
        // mesa do planejamento). Cancelamento de draft/requested não vale
        // ticket.
        if (! in_array($event->toStatus, [RelocationStatus::REJECTED, RelocationStatus::CANCELLED], true)) {
            return;
        }

        if ($event->toStatus === RelocationStatus::CANCELLED
            && in_array($event->fromStatus, [RelocationStatus::DRAFT, RelocationStatus::REQUESTED], true)) {
            // Cancelamento prematuro — não abre ticket
            return;
        }

        $relocation = $event->relocation;

        if ($relocation->helpdesk_ticket_id) {
            return; // Idempotente
        }

        if (! Schema::hasTable('hd_departments') || ! Schema::hasTable('hd_tickets')) {
            return; // Módulo Helpdesk não instalado neste tenant
        }

        try {
            $department = HdDepartment::query()
                ->active()
                ->whereRaw('LOWER(name) = ?', ['logística'])
                ->first();

            if (! $department) {
                // Tenta sem acento como fallback
                $department = HdDepartment::query()
                    ->active()
                    ->whereRaw('LOWER(name) = ?', ['logistica'])
                    ->first();
            }

            if (! $department) {
                Log::info('Relocation Helpdesk hook skipped: no active Logística department', [
                    'relocation_id' => $relocation->id,
                ]);
                return;
            }

            $action = $event->toStatus === RelocationStatus::REJECTED ? 'Rejeição' : 'Cancelamento';
            $originCode = $relocation->originStore?->code ?? '—';
            $destCode = $relocation->destinationStore?->code ?? '—';

            $title = sprintf(
                '%s de remanejo #%d — %s → %s',
                $action,
                $relocation->id,
                $originCode,
                $destCode
            );

            $description = sprintf(
                "%s de remanejo registrada automaticamente.\n\n"
                ."• Remanejo: #%d (%s)\n"
                ."• Tipo: %s\n"
                ."• Origem: %s\n"
                ."• Destino: %s\n"
                ."• Estado anterior: %s\n"
                ."• Estado atual: %s\n"
                ."• Motivo informado: %s\n"
                ."• Atuante: %s\n\n"
                ."Avalie se há ação corretiva ou se o motivo precisa ser tratado com a equipe de planejamento.\n\n"
                ."Detalhes em /relocations/%s",
                $action,
                $relocation->id,
                $relocation->title ?? '(sem título)',
                $relocation->type?->name ?? '—',
                $originCode,
                $destCode,
                $event->fromStatus->label(),
                $event->toStatus->label(),
                $event->note ?? '(não informado)',
                $event->actor->name ?? '—',
                $relocation->ulid
            );

            $ticket = $this->helpdesk->createTicket(
                [
                    'department_id' => $department->id,
                    'title' => $title,
                    'description' => $description,
                    'priority' => 2, // Normal
                    'source' => 'system',
                ],
                $event->actor->id
            );

            $relocation->update(['helpdesk_ticket_id' => $ticket->id]);
        } catch (\Throwable $e) {
            Log::warning('Failed to open Helpdesk ticket for relocation', [
                'relocation_id' => $relocation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
