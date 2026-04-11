<?php

namespace App\Console\Commands;

use App\Jobs\SendExperienceNotificationJob;
use App\Models\ExperienceEvaluation;
use App\Models\ExperienceNotification;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExperienceNotificationsCommand extends Command
{
    protected $signature = 'experience:notify
                           {--dry-run : Listar avaliações sem enviar notificações}';

    protected $description = 'Verifica avaliações de experiência pendentes e envia notificações (criação, lembrete 5d, vencimento, atraso)';

    public function handle(): int
    {
        $this->info('Verificando avaliações de experiência...');

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $totalDispatched = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($dryRun, &$totalDispatched) {
                    $totalDispatched += $this->processNotifications($dryRun);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Base table or view not found')) {
                    $this->warn('  Tabelas de experiência não encontradas (execute migrations).');
                } else {
                    $this->error("  Erro: {$e->getMessage()}");
                    Log::error('ExperienceNotificationsCommand tenant error', [
                        'tenant' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Total de notificações despachadas: {$totalDispatched}");

        Log::info('ExperienceNotificationsCommand completed', ['dispatched' => $totalDispatched]);

        return self::SUCCESS;
    }

    protected function processNotifications(bool $dryRun): int
    {
        $dispatched = 0;

        // 1. New evaluations without "created" notification
        $newEvals = ExperienceEvaluation::pending()
            ->whereDoesntHave('notifications', fn ($q) => $q->where('notification_type', ExperienceNotification::TYPE_CREATED))
            ->get();

        foreach ($newEvals as $eval) {
            $this->line("  [CREATED] {$eval->employee?->name} - {$eval->milestone_label}");
            if (! $dryRun) {
                SendExperienceNotificationJob::dispatch($eval->id, ExperienceNotification::TYPE_CREATED);
            }
            $dispatched++;
        }

        // 2. Evaluations due in 5 days without "reminder_5d" notification
        $nearDeadline = ExperienceEvaluation::nearDeadline(5)
            ->whereDoesntHave('notifications', fn ($q) => $q->where('notification_type', ExperienceNotification::TYPE_REMINDER_5D))
            ->get();

        foreach ($nearDeadline as $eval) {
            $this->line("  [5D REMINDER] {$eval->employee?->name} - {$eval->milestone_label} (prazo: {$eval->milestone_date->format('d/m/Y')})");
            if (! $dryRun) {
                SendExperienceNotificationJob::dispatch($eval->id, ExperienceNotification::TYPE_REMINDER_5D);
            }
            $dispatched++;
        }

        // 3. Evaluations due today without "reminder_due" notification
        $dueToday = ExperienceEvaluation::pending()
            ->whereDate('milestone_date', now()->toDateString())
            ->whereDoesntHave('notifications', fn ($q) => $q->where('notification_type', ExperienceNotification::TYPE_REMINDER_DUE))
            ->get();

        foreach ($dueToday as $eval) {
            $this->line("  [DUE TODAY] {$eval->employee?->name} - {$eval->milestone_label}");
            if (! $dryRun) {
                SendExperienceNotificationJob::dispatch($eval->id, ExperienceNotification::TYPE_REMINDER_DUE);
            }
            $dispatched++;
        }

        // 4. Overdue evaluations without "overdue" notification
        $overdue = ExperienceEvaluation::overdue()
            ->whereDoesntHave('notifications', fn ($q) => $q->where('notification_type', ExperienceNotification::TYPE_OVERDUE))
            ->get();

        foreach ($overdue as $eval) {
            $daysOverdue = now()->diffInDays($eval->milestone_date);
            $this->warn("  [OVERDUE] {$eval->employee?->name} - {$eval->milestone_label} ({$daysOverdue} dias atrasado)");
            if (! $dryRun) {
                SendExperienceNotificationJob::dispatch($eval->id, ExperienceNotification::TYPE_OVERDUE);
            }
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->line('  Nenhuma notificação pendente.');
        }

        return $dispatched;
    }
}
