<?php

namespace App\Console\Commands;

use App\Enums\ConsignmentStatus;
use App\Enums\Permission;
use App\Models\Consignment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ConsignmentReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Lembrete preventivo — envia alerta consolidado para o criador e
 * gerentes da loja quando há consignações abertas com prazo a ≤ N dias
 * de vencer (default 2).
 *
 * Schedule sugerido: daily 09:00 — chega no início do expediente pra
 * que o consultor contate o destinatário antes de virar overdue.
 *
 * Agrupa por loja e envia 1 notificação consolidada por gerente/criador
 * em vez de 1 notificação por consignação (reduz ruído).
 */
class ConsignmentsRemindUpcomingCommand extends Command
{
    protected $signature = 'consignments:remind-upcoming
                            {--days=2 : Dias de antecedência para o alerta}';

    protected $description = 'Lembrete de consignações com prazo a vencer em ≤ N dias.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $this->info("Consignment Reminder — threshold: ≤ {$days} dia(s)");

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
        $this->info("Total de notificações enviadas: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * @return int Número de notificações enviadas
     */
    public function scanTenant(int $days): int
    {
        if (! Schema::hasTable('consignments')) {
            return 0;
        }

        $today = now()->toDateString();
        $deadline = now()->addDays($days)->toDateString();

        $upcoming = Consignment::query()
            ->notDeleted()
            ->whereIn('status', [
                ConsignmentStatus::PENDING->value,
                ConsignmentStatus::PARTIALLY_RETURNED->value,
            ])
            ->whereBetween('expected_return_date', [$today, $deadline])
            ->get();

        if ($upcoming->isEmpty()) {
            $this->line('  Nada no horizonte.');

            return 0;
        }

        $sent = 0;
        $byStore = $upcoming->groupBy('store_id');

        foreach ($byStore as $storeId => $consignments) {
            $creatorIds = $consignments->pluck('created_by_user_id')->filter()->unique();

            // Gerentes da loja (MANAGE_CONSIGNMENTS)
            $managerIds = User::all()
                ->filter(fn (User $u) => $u->hasPermissionTo(Permission::MANAGE_CONSIGNMENTS->value))
                ->pluck('id');

            $recipientIds = $creatorIds->merge($managerIds)->unique()->values();
            $recipients = User::query()->whereIn('id', $recipientIds)->get();

            if ($recipients->isEmpty()) {
                $this->warn("  [store #{$storeId}] sem destinatários — {$consignments->count()} pendente(s)");
                continue;
            }

            $payload = $this->buildPayload($consignments);

            try {
                Notification::send($recipients, new ConsignmentReminderNotification(
                    consignments: $payload,
                    days: $days,
                    kind: 'upcoming',
                ));
                $sent += $recipients->count();
                $this->line("  [store #{$storeId}] {$consignments->count()} próxima(s) → {$recipients->count()} destinatário(s)");
            } catch (\Throwable $e) {
                $this->warn("  [store #{$storeId}] falha ao notificar: {$e->getMessage()}");
            }
        }

        return $sent;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildPayload($consignments): array
    {
        return $consignments->map(fn (Consignment $c) => [
            'id' => $c->id,
            'recipient_name' => $c->recipient_name,
            'outbound_invoice_number' => $c->outbound_invoice_number,
            'outbound_total_value' => (float) $c->outbound_total_value,
            'type_label' => $c->type?->label() ?? '—',
            'expected_return_date' => $c->expected_return_date?->format('Y-m-d'),
            'days_to_deadline' => (int) now()->diffInDays($c->expected_return_date, false),
        ])->values()->all();
    }
}
