<?php

namespace App\Console\Commands;

use App\Models\HdTicket;
use App\Models\Tenant;
use App\Services\HelpdeskSlaCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Recalculates sla_due_at for existing tickets using the business-hours
 * calculator. Intended to be run once after enabling business-hours mode
 * or after editing hd_business_hours / hd_holidays for a department.
 *
 * Iterates tenants using Tenant::run() for context isolation — same pattern
 * as HelpdeskSlaMonitorCommand and HelpdeskRelocateAttachmentsCommand.
 *
 *   php artisan helpdesk:recalculate-sla --dry-run
 *   php artisan helpdesk:recalculate-sla --tenant=acme --department=1
 */
class HelpdeskRecalculateSlaCommand extends Command
{
    protected $signature = 'helpdesk:recalculate-sla
        {--tenant= : Only run for a specific tenant id}
        {--department= : Limit to a specific department id}
        {--include-terminal : Also recalculate closed/cancelled tickets}
        {--dry-run : Show changes without writing}';

    protected $description = 'Recalculates sla_due_at for existing tickets using the current business-hours schedule.';

    public function handle(HelpdeskSlaCalculator $calculator): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId
            ? Tenant::query()->where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? '[DRY-RUN] Nada será modificado.' : 'Modo ativo: sla_due_at será atualizado.');

        $totalChanged = 0;

        foreach ($tenants as $tenant) {
            $this->line("→ Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($calculator, $dryRun, &$totalChanged) {
                    if (! Schema::hasTable('hd_tickets')) {
                        $this->warn('  Tabela hd_tickets não encontrada.');

                        return;
                    }

                    $totalChanged += $this->processCurrentContext($calculator, $dryRun);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Base table or view not found')) {
                    $this->warn('  Tabelas do helpdesk não encontradas.');
                } else {
                    $this->error("  Erro: {$e->getMessage()}");
                    Log::error('HelpdeskRecalculateSlaCommand tenant error', [
                        'tenant' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Total de atualizações: {$totalChanged}");

        return self::SUCCESS;
    }

    private function processCurrentContext(HelpdeskSlaCalculator $calculator, bool $dryRun): int
    {
        $query = HdTicket::query()->with('department');

        if ($departmentId = $this->option('department')) {
            $query->where('department_id', (int) $departmentId);
        }

        if (! $this->option('include-terminal')) {
            $query->whereNotIn('status', HdTicket::TERMINAL_STATUSES);
        }

        $tickets = $query->get();
        $this->line("  Processando {$tickets->count()} chamado(s)…");

        $changed = 0;
        foreach ($tickets as $ticket) {
            $slaHours = HdTicket::SLA_HOURS[$ticket->priority] ?? 48;
            $newDue = $calculator->calculateDueDate(
                $ticket->created_at->copy(),
                $slaHours,
                $ticket->department,
            );

            if ((string) $newDue === (string) $ticket->sla_due_at) {
                continue;
            }

            $this->line(sprintf(
                '  #%d  %s → %s',
                $ticket->id,
                optional($ticket->sla_due_at)->format('Y-m-d H:i') ?? 'null',
                $newDue->format('Y-m-d H:i'),
            ));

            if (! $dryRun) {
                $ticket->update(['sla_due_at' => $newDue]);
            }

            $changed++;
        }

        $this->line("  Resultado do tenant: {$changed} atualização(ões).");

        return $changed;
    }
}
