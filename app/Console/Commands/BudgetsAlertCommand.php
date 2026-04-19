<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BudgetAlertNotification;
use App\Services\BudgetAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Varre budgets ativos do ano corrente em cada tenant e envia alerta
 * consolidado para usuários com permission VIEW_BUDGET_CONSUMPTION
 * quando há CCs/items em warning (≥70%) ou exceeded (≥100%).
 *
 * Schedule sugerido: daily 09:00 — chega no início do expediente para
 * que o financeiro possa agir antes das decisões de compra do dia.
 */
class BudgetsAlertCommand extends Command
{
    protected $signature = 'budgets:alert
                            {--year= : Ano do budget (default: ano corrente)}
                            {--dry-run : Apenas imprime o que seria enviado}';

    protected $description = 'Alerta diário de orçamentos com consumo ≥ 70% ou ≥ 100%.';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Budget Alert — year: {$year}".($dryRun ? ' (DRY RUN)' : ''));

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($year, $dryRun, &$grandTotal) {
                    $grandTotal += $this->scanTenant($year, $dryRun);
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total de notificações enviadas: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * Escaneia o tenant atual e dispara os alertas. Extraído do handle()
     * para testabilidade (SQLite in-memory não tem tenants registrados).
     *
     * @return int Número de notificações enviadas
     */
    public function scanTenant(int $year, bool $dryRun = false): int
    {
        if (! Schema::hasTable('budget_uploads')) {
            return 0;
        }

        $alertService = app(BudgetAlertService::class);
        $scan = $alertService->scanAlerts($year);

        $scanned = $scan['summary']['scanned_budgets'];
        $warning = $scan['summary']['warning_count'];
        $exceeded = $scan['summary']['exceeded_count'];

        $this->safeLine("  {$scanned} budgets escaneados · {$warning} warning · {$exceeded} exceeded");

        if (! $alertService->hasAlerts($scan)) {
            return 0;
        }

        $recipients = $this->resolveRecipients();

        if ($recipients->isEmpty()) {
            $this->safeLine('  Sem recipients (ninguém com VIEW_BUDGET_CONSUMPTION).');

            return 0;
        }

        if ($dryRun) {
            $this->safeLine("  [DRY RUN] enviaria para {$recipients->count()} usuário(s)");

            return 0;
        }

        try {
            Notification::send($recipients, new BudgetAlertNotification($scan));
        } catch (\Throwable $e) {
            $this->safeLine("  Falha no envio: {$e->getMessage()}");

            return 0;
        }

        return $recipients->count();
    }

    /**
     * Wrapper seguro para when o command é instanciado sem Artisan
     * (direto em testes). Sem isso, `$this->output` é null e a chamada
     * explode com "writeln on null".
     */
    protected function safeLine(string $message): void
    {
        if ($this->output !== null) {
            $this->line($message);
        }
    }

    /**
     * Usuários ativos com permission VIEW_BUDGET_CONSUMPTION.
     */
    protected function resolveRecipients()
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        // Tabela users não tem deleted_at — sem soft delete de usuários
        return User::query()
            ->get()
            ->filter(fn (User $u) => $u->hasPermissionTo(Permission::VIEW_BUDGET_CONSUMPTION));
    }
}
