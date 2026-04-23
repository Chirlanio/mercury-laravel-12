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
 * Alerta crítico — consignações em status `overdue` há ≥ N dias
 * (default 7). Notifica gerentes com MANAGE_CONSIGNMENTS para
 * escalonar (cancelar, cobrar cliente, ajustar prazo, etc.).
 *
 * Schedule sugerido: daily 09:00 — chega junto do remind-upcoming
 * mas com mensagem distinta.
 *
 * Agrupa por loja; 1 notificação consolidada por gerente.
 */
class ConsignmentsOverdueAlertCommand extends Command
{
    protected $signature = 'consignments:overdue-alert
                            {--days=7 : Mínimo de dias em overdue para alertar}';

    protected $description = 'Alerta crítico de consignações em overdue há ≥ N dias.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $this->info("Consignment Overdue Alert — threshold: ≥ {$days} dia(s) em overdue");

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

        // "Em overdue há ≥ N dias" — usa expected_return_date porque
        // ela é o anchor temporal estável (status pode ter oscilado
        // entre overdue e partially_returned quando houve retorno parcial).
        $threshold = now()->subDays($days)->toDateString();

        $overdue = Consignment::query()
            ->notDeleted()
            ->where('status', ConsignmentStatus::OVERDUE->value)
            ->where('expected_return_date', '<=', $threshold)
            ->get();

        if ($overdue->isEmpty()) {
            $this->line('  Sem atrasos críticos.');

            return 0;
        }

        // Destinatários = gerentes com MANAGE_CONSIGNMENTS (supervisão)
        $managers = User::all()
            ->filter(fn (User $u) => $u->hasPermissionTo(Permission::MANAGE_CONSIGNMENTS->value))
            ->values();

        if ($managers->isEmpty()) {
            $this->warn('  Sem gerentes com MANAGE_CONSIGNMENTS — ninguém notificado.');

            return 0;
        }

        $payload = $overdue->map(fn (Consignment $c) => [
            'id' => $c->id,
            'recipient_name' => $c->recipient_name,
            'outbound_invoice_number' => $c->outbound_invoice_number,
            'outbound_total_value' => (float) $c->outbound_total_value,
            'type_label' => $c->type?->label() ?? '—',
            'expected_return_date' => $c->expected_return_date?->format('Y-m-d'),
            'days_overdue' => (int) $c->expected_return_date?->diffInDays(now()),
            'store_id' => $c->store_id,
        ])->values()->all();

        try {
            Notification::send($managers, new ConsignmentReminderNotification(
                consignments: $payload,
                days: $days,
                kind: 'overdue',
            ));
            $this->line("  {$overdue->count()} em atraso crítico → {$managers->count()} gerente(s) notificado(s)");

            return $managers->count();
        } catch (\Throwable $e) {
            $this->warn("  Falha ao notificar: {$e->getMessage()}");

            return 0;
        }
    }
}
