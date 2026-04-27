<?php

namespace App\Listeners;

use App\Enums\RelocationStatus;
use App\Events\RelocationStatusChanged;
use App\Models\HdDepartment;
use App\Services\HelpdeskService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Hook opcional: quando um remanejo é confirmado em IN_TRANSIT com
 * divergências entre os items separados e a NF emitida no CIGAM, abre
 * automaticamente um ticket no departamento Logística do Helpdesk pra
 * documentar/investigar a causa.
 *
 * Idempotente via coluna dedicada `dispatch_helpdesk_ticket_id` —
 * separada de `helpdesk_ticket_id` (usada pelo hook de rejeição/cancel
 * do OpenHelpdeskTicketForRelocation), permitindo que o mesmo remanejo
 * tenha 2 tickets se cair em ambos os fluxos.
 *
 * Fail-safe: 3 níveis (schema check, depto Logística existe, try/catch).
 *
 * Auto-discovered via type-hint do `handle(RelocationStatusChanged $e)`
 * — NÃO registrar via Event::listen manual.
 */
class OpenHelpdeskTicketForDispatchDiscrepancies
{
    public function __construct(protected HelpdeskService $helpdesk) {}

    public function handle(RelocationStatusChanged $event): void
    {
        if ($event->toStatus !== RelocationStatus::IN_TRANSIT) {
            return;
        }

        $relocation = $event->relocation;

        if (! $relocation->dispatch_has_discrepancies) {
            return;
        }

        if ($relocation->dispatch_helpdesk_ticket_id) {
            return; // Idempotente
        }

        if (! Schema::hasTable('hd_departments') || ! Schema::hasTable('hd_tickets')) {
            return; // Módulo Helpdesk não instalado neste tenant
        }

        try {
            $department = HdDepartment::query()
                ->active()
                ->whereRaw('LOWER(name) = ?', ['logística'])
                ->first()
                ?? HdDepartment::query()
                    ->active()
                    ->whereRaw('LOWER(name) = ?', ['logistica'])
                    ->first();

            if (! $department) {
                Log::info('Dispatch discrepancy Helpdesk hook skipped: no active Logística department', [
                    'relocation_id' => $relocation->id,
                ]);
                return;
            }

            $diag = $relocation->dispatch_discrepancies_json ?? [];
            $missingCount = count($diag['missing'] ?? []);
            $extraCount = count($diag['extra'] ?? []);
            $divergentCount = count($diag['divergent'] ?? []);

            $relocation->loadMissing(['originStore', 'destinationStore', 'type']);
            $originCode = $relocation->originStore?->code ?? '—';
            $destCode = $relocation->destinationStore?->code ?? '—';

            $title = sprintf(
                'Divergência de despacho — Remanejo #%d (%s → %s) — NF %s',
                $relocation->id,
                $originCode,
                $destCode,
                $relocation->invoice_number ?? '—',
            );

            $description = sprintf(
                "Divergência detectada automaticamente entre os itens separados e a NF emitida.\n\n"
                ."• Remanejo: #%d (%s)\n"
                ."• Tipo: %s\n"
                ."• Origem: %s\n"
                ."• Destino: %s\n"
                ."• NF: %s (data %s)\n"
                ."• Itens faltando na NF: %d\n"
                ."• Itens sobrando na NF: %d\n"
                ."• Itens com qty divergente: %d\n"
                ."• Confirmado por: %s\n\n"
                ."Investigue a separação física, a NF emitida no CIGAM ou contate a loja origem.\n\n"
                ."Detalhes em /relocations (filtrar por status In Transit, NF %s).",
                $relocation->id,
                $relocation->title ?? '(sem título)',
                $relocation->type?->name ?? '—',
                $originCode,
                $destCode,
                $relocation->invoice_number ?? '—',
                $relocation->invoice_date?->format('d/m/Y') ?? '—',
                $missingCount,
                $extraCount,
                $divergentCount,
                $event->actor->name ?? '—',
                $relocation->invoice_number ?? '—',
            );

            $ticket = $this->helpdesk->createTicket(
                [
                    'department_id' => $department->id,
                    'title' => $title,
                    'description' => $description,
                    'priority' => 3, // Alta — divergência precisa investigação rápida
                    'source' => 'system',
                ],
                $event->actor->id
            );

            $relocation->update(['dispatch_helpdesk_ticket_id' => $ticket->id]);
        } catch (\Throwable $e) {
            Log::warning('Failed to open Helpdesk ticket for dispatch discrepancy', [
                'relocation_id' => $relocation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
