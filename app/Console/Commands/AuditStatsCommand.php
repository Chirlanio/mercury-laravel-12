<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AuditStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:stats
                           {--days=30 : Número de dias para análise (padrão: 30)}
                           {--export : Exportar estatísticas para arquivo JSON}
                           {--format=table : Formato de saída (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exibe estatísticas detalhadas dos logs de auditoria';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $export = $this->option('export');
        $format = $this->option('format');

        if ($days < 1) {
            $this->error('O número de dias deve ser maior que 0');
            return 1;
        }

        $auditService = app(AuditLogService::class);
        $stats = $auditService->getAuditStatistics($days);

        $this->info("Estatísticas de Auditoria - Últimos {$days} dias");
        $this->info("Período: " . Carbon::now()->subDays($days)->format('d/m/Y') . " até " . Carbon::now()->format('d/m/Y'));
        $this->newLine();

        if ($format === 'json') {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        // Estatísticas gerais
        $this->info('📊 RESUMO GERAL');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de Ações', number_format($stats['total_actions'])],
                ['Usuários Únicos', number_format($stats['unique_users'])],
                ['Média de Ações/Dia', number_format($stats['total_actions'] / $days, 1)],
            ]
        );
        $this->newLine();

        // Ações por tipo
        if (!empty($stats['actions_by_type'])) {
            $this->info('🎯 AÇÕES POR TIPO');
            $actionData = [];
            foreach ($stats['actions_by_type'] as $action => $count) {
                $percentage = ($count / $stats['total_actions']) * 100;
                $actionData[] = [
                    $action,
                    number_format($count),
                    number_format($percentage, 1) . '%'
                ];
            }
            $this->table(['Ação', 'Quantidade', 'Percentual'], $actionData);
            $this->newLine();
        }

        // Usuários mais ativos
        if (!empty($stats['most_active_users'])) {
            $this->info('👥 USUÁRIOS MAIS ATIVOS (Top 10)');
            $userData = [];
            $rank = 1;
            foreach ($stats['most_active_users'] as $userId => $count) {
                $user = \App\Models\User::find($userId);
                $userData[] = [
                    $rank++,
                    $user ? $user->name : "ID: {$userId}",
                    number_format($count),
                    number_format(($count / $stats['total_actions']) * 100, 1) . '%'
                ];
            }
            $this->table(['Rank', 'Usuário', 'Ações', 'Percentual'], $userData);
            $this->newLine();
        }

        // Atividade por dia (últimos 7 dias)
        if (!empty($stats['actions_by_day'])) {
            $this->info('📅 ATIVIDADE POR DIA (Últimos 7 dias)');
            $dayData = [];
            $recentDays = collect($stats['actions_by_day'])
                ->sortKeysDesc()
                ->take(7)
                ->reverse();

            foreach ($recentDays as $date => $count) {
                $dayOfWeek = Carbon::parse($date)->locale('pt_BR')->dayName;
                $dayData[] = [
                    Carbon::parse($date)->format('d/m/Y'),
                    $dayOfWeek,
                    number_format($count),
                    $this->generateBar($count, $recentDays->max(), 20)
                ];
            }
            $this->table(['Data', 'Dia da Semana', 'Ações', 'Gráfico'], $dayData);
            $this->newLine();
        }

        // Atividade por hora
        if (!empty($stats['actions_per_hour'])) {
            $this->info('🕐 ATIVIDADE POR HORA DO DIA');
            $hourData = [];
            $maxHourActivity = collect($stats['actions_per_hour'])->max();

            for ($hour = 0; $hour < 24; $hour++) {
                $count = $stats['actions_per_hour'][$hour] ?? 0;
                $hourData[] = [
                    sprintf('%02d:00', $hour),
                    number_format($count),
                    $this->generateBar($count, $maxHourActivity, 15)
                ];
            }
            $this->table(['Hora', 'Ações', 'Gráfico'], $hourData);
            $this->newLine();
        }

        // Exportar se solicitado
        if ($export) {
            $filename = storage_path('app/audit_stats_' . now()->format('Y-m-d_H-i-s') . '.json');
            file_put_contents($filename, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("📁 Estatísticas exportadas para: {$filename}");
        }

        return 0;
    }

    /**
     * Gera uma barra de progresso visual para os gráficos
     */
    private function generateBar(int $value, int $max, int $length = 20): string
    {
        if ($max === 0) {
            return str_repeat('░', $length);
        }

        $filled = (int) round(($value / $max) * $length);
        $empty = $length - $filled;

        return str_repeat('█', $filled) . str_repeat('░', $empty);
    }
}
