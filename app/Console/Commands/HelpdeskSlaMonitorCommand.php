<?php

namespace App\Console\Commands;

use App\Models\HdInteraction;
use App\Models\HdPermission;
use App\Models\HdTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Helpdesk\SlaBreachedNotification;
use App\Notifications\Helpdesk\SlaBreachWarningNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class HelpdeskSlaMonitorCommand extends Command
{
    protected $signature = 'helpdesk:sla-monitor
                           {--dry-run : Lista tickets sem enviar notificações}';

    protected $description = 'Monitora chamados próximos do SLA e envia alertas (aviso 2h antes e breach).';

    /** Minutes of warning lead time before SLA breach. */
    protected const WARNING_LEAD_HOURS = 2;

    public function handle(): int
    {
        $this->info('Verificando SLAs de chamados...');

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $totalWarnings = 0;
        $totalBreaches = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($dryRun, &$totalWarnings, &$totalBreaches) {
                    if (! Schema::hasTable('hd_tickets')) {
                        $this->warn('  Tabelas do helpdesk não encontradas (execute migrations).');

                        return;
                    }

                    $totalWarnings += $this->processWarnings($dryRun);
                    $totalBreaches += $this->processBreaches($dryRun);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Base table or view not found')) {
                    $this->warn('  Tabelas do helpdesk não encontradas.');
                } else {
                    $this->error("  Erro: {$e->getMessage()}");
                    Log::error('HelpdeskSlaMonitorCommand tenant error', [
                        'tenant' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Avisos enviados: {$totalWarnings} · Breaches notificados: {$totalBreaches}");

        Log::info('HelpdeskSlaMonitorCommand completed', [
            'warnings' => $totalWarnings,
            'breaches' => $totalBreaches,
        ]);

        return self::SUCCESS;
    }

    /**
     * Notify tickets approaching SLA (within WARNING_LEAD_HOURS).
     */
    protected function processWarnings(bool $dryRun): int
    {
        $count = 0;
        $threshold = now()->addHours(self::WARNING_LEAD_HOURS);

        $tickets = HdTicket::active()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '>', now())
            ->where('sla_due_at', '<=', $threshold)
            ->whereDoesntHave('interactions', fn ($q) => $q->where('type', 'sla_warning'))
            ->with(['department', 'assignedTechnician'])
            ->get();

        foreach ($tickets as $ticket) {
            $this->line("  [WARNING] #{$ticket->id} - {$ticket->title} (vence em {$ticket->sla_remaining_hours}h)");

            if ($dryRun) {
                $count++;

                continue;
            }

            $recipients = $this->resolveTicketRecipients($ticket);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new SlaBreachWarningNotification($ticket));
            }

            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->assigned_technician_id ?? $ticket->requester_id,
                'comment' => 'Aviso automático: SLA próximo do vencimento.',
                'type' => 'sla_warning',
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Notify tickets that have breached their SLA.
     */
    protected function processBreaches(bool $dryRun): int
    {
        $count = 0;

        $tickets = HdTicket::overdue()
            ->whereDoesntHave('interactions', fn ($q) => $q->where('type', 'sla_breach'))
            ->with(['department', 'assignedTechnician'])
            ->get();

        foreach ($tickets as $ticket) {
            $this->warn("  [BREACH] #{$ticket->id} - {$ticket->title} (vencido)");

            if ($dryRun) {
                $count++;

                continue;
            }

            $recipients = $this->resolveTicketRecipients($ticket);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new SlaBreachedNotification($ticket));
            }

            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->assigned_technician_id ?? $ticket->requester_id,
                'comment' => 'SLA vencido.',
                'type' => 'sla_breach',
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Resolve recipients for SLA notifications: assigned technician + all department managers.
     */
    protected function resolveTicketRecipients(HdTicket $ticket)
    {
        $ids = [];

        if ($ticket->assigned_technician_id) {
            $ids[] = $ticket->assigned_technician_id;
        }

        $managerIds = HdPermission::where('department_id', $ticket->department_id)
            ->where('level', 'manager')
            ->pluck('user_id')
            ->toArray();

        $ids = array_unique(array_merge($ids, $managerIds));

        return User::whereIn('id', $ids)->get();
    }
}
