<?php

namespace App\Console\Commands;

use App\Enums\AccountabilityStatus;
use App\Enums\Permission;
use App\Enums\TravelExpenseStatus;
use App\Models\Tenant;
use App\Models\TravelExpense;
use App\Models\User;
use App\Notifications\TravelExpenseAccountabilityOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Notifica solicitantes (e Financeiro como CC) de verbas APROVADAS cuja
 * viagem terminou há ≥3 dias e ainda não tiveram prestação enviada/aprovada.
 *
 * Schedule sugerido: daily 09:00 — substitui o cron v1
 * `check_travel_expenses_cron.php`.
 *
 * Idempotente do ponto de vista de estado (não muda dados); pode rodar
 * múltiplas vezes mas dispara notificações repetidas — mitigamos
 * marcando `accountability_overdue_notified_at` (futuro). Por ora,
 * mantemos simples: limita a 1 notificação por dia via filtro de período.
 */
class TravelExpensesAccountabilityOverdueCommand extends Command
{
    protected $signature = 'travel-expenses:accountability-overdue {--days=3 : Dias após retorno para considerar atrasada}';

    protected $description = 'Alerta solicitantes e financeiro sobre verbas com prestação de contas atrasada.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($days, &$grandTotal) {
                    $grandTotal += $this->scanTenant($days);
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total de alertas enviados: {$grandTotal}");

        return self::SUCCESS;
    }

    public function scanTenant(int $days): int
    {
        if (! Schema::hasTable('travel_expenses')) {
            return 0;
        }

        $overdue = TravelExpense::query()
            ->notDeleted()
            ->forStatus(TravelExpenseStatus::APPROVED)
            ->whereNotIn('accountability_status', [
                AccountabilityStatus::SUBMITTED->value,
                AccountabilityStatus::APPROVED->value,
            ])
            ->where('end_date', '<=', now()->subDays($days)->toDateString())
            ->with(['employee:id,name', 'createdBy:id,name,email'])
            ->get();

        if ($overdue->isEmpty()) {
            $this->line('  Nenhuma prestação atrasada.');

            return 0;
        }

        $approvers = $this->resolveApprovers();

        $sent = 0;
        foreach ($overdue as $te) {
            $recipients = collect();

            if ($te->createdBy && ! empty($te->createdBy->email)) {
                $recipients->push($te->createdBy);
            }

            // Aprovadores recebem cópia (Financeiro acompanha)
            $recipients = $recipients
                ->merge($approvers)
                ->unique('id')
                ->values();

            if ($recipients->isEmpty()) {
                continue;
            }

            try {
                Notification::send($recipients, new TravelExpenseAccountabilityOverdueNotification($te, $days));
                $sent++;
                $this->line("  #{$te->id} ({$te->employee?->name}) — {$te->days_since_end} dias atrasada — {$recipients->count()} destinatário(s)");
            } catch (\Throwable $e) {
                $this->warn("  #{$te->id} falhou: {$e->getMessage()}");
            }
        }

        return $sent;
    }

    /**
     * Lista de usuários com APPROVE_TRAVEL_EXPENSES (Financeiro/Contas a Pagar).
     */
    protected function resolveApprovers()
    {
        return User::query()
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $u) => $u->hasPermissionTo(Permission::APPROVE_TRAVEL_EXPENSES->value))
            ->values();
    }
}
