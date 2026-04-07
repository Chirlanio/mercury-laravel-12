<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreGoal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StoreGoalsMidMonthAlert extends Command
{
    protected $signature = 'store-goals:midmonth-alert
                           {--threshold=55 : Percentual mínimo de atingimento (padrão: 55%)}
                           {--dry-run : Apenas listar lojas sem enviar e-mails}';

    protected $description = 'Verifica metas de loja no meio do mês e alerta lojas com atingimento abaixo do limiar';

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $dryRun = $this->option('dry-run');

        $this->info("Verificando metas com atingimento < {$threshold}%...");

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            $tenant->run(function () use ($threshold, $dryRun) {
                $this->processAlerts($threshold, $dryRun);
            });
        }

        return self::SUCCESS;
    }

    protected function processAlerts(float $threshold, bool $dryRun): void
    {
        $month = now()->month;
        $year = now()->year;

        $goals = StoreGoal::forMonth($month, $year)->with('store')->get();

        if ($goals->isEmpty()) {
            $this->line('  Nenhuma meta cadastrada para o mês atual.');

            return;
        }

        $alerts = [];

        foreach ($goals as $goal) {
            $storeSales = (float) Sale::forMonth($month, $year)
                ->forStoreWithEcommerce($goal->store_id)
                ->sum('total_sales');

            $pct = $goal->goal_amount > 0
                ? round(($storeSales / $goal->goal_amount) * 100, 1)
                : 0;

            if ($pct < $threshold) {
                $storeName = $goal->store->display_name ?? $goal->store->name;
                $alerts[] = [
                    'store_name' => $storeName,
                    'goal_amount' => $goal->goal_amount,
                    'sales' => round($storeSales, 2),
                    'achievement_pct' => $pct,
                    'missing' => round($goal->goal_amount - $storeSales, 2),
                ];

                $this->warn("  {$storeName}: {$pct}% (Meta: " . number_format($goal->goal_amount, 2, ',', '.') . ")");
            }
        }

        if (empty($alerts)) {
            $this->info('  Todas as lojas estão acima do limiar.');

            return;
        }

        $this->line("  {$alerts[0]['store_name']} e mais " . (count($alerts) - 1) . ' lojas abaixo de ' . $threshold . '%');

        if ($dryRun) {
            $this->info('  [dry-run] Nenhum e-mail enviado.');

            return;
        }

        // Send alert email to admins
        $admins = User::whereIn('role', ['super_admin', 'admin'])->get();

        if ($admins->isEmpty()) {
            $this->warn('  Nenhum administrador encontrado para notificar.');

            return;
        }

        $monthNames = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
        $periodLabel = $monthNames[$month] . '/' . $year;

        foreach ($admins as $admin) {
            try {
                Mail::send([], [], function ($message) use ($admin, $alerts, $threshold, $periodLabel) {
                    $body = "Alerta de Meio de Mês - {$periodLabel}\n\n";
                    $body .= count($alerts) . " loja(s) com atingimento abaixo de {$threshold}%:\n\n";

                    foreach ($alerts as $alert) {
                        $body .= "• {$alert['store_name']}: {$alert['achievement_pct']}% ";
                        $body .= '(Vendas: R$ ' . number_format($alert['sales'], 2, ',', '.');
                        $body .= ' / Meta: R$ ' . number_format($alert['goal_amount'], 2, ',', '.') . ")\n";
                        $body .= '  Falta: R$ ' . number_format($alert['missing'], 2, ',', '.') . "\n\n";
                    }

                    $body .= "\n--\nMercury - Sistema de Gestão";

                    $message->to($admin->email)
                        ->subject("[Mercury] Alerta: " . count($alerts) . " loja(s) abaixo de {$threshold}% - {$periodLabel}")
                        ->text($body);
                });

                $this->info("  E-mail enviado para {$admin->email}");
            } catch (\Exception $e) {
                $this->error("  Erro ao enviar para {$admin->email}: {$e->getMessage()}");
                Log::error('MidMonthAlert email failed', [
                    'admin' => $admin->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('StoreGoals mid-month alert', [
            'stores_below_threshold' => count($alerts),
            'threshold' => $threshold,
        ]);
    }
}
