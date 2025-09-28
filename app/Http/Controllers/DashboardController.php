<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Estatísticas gerais
        $stats = [
            'total_users' => User::count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
            'new_users_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        // Estatísticas de atividade
        $activityStats = [
            'total_activities_today' => ActivityLog::whereDate('created_at', today())->count(),
            'total_activities_week' => ActivityLog::where('created_at', '>=', now()->startOfWeek())->count(),
            'unique_active_users_today' => ActivityLog::whereDate('created_at', today())
                ->distinct('user_id')->count('user_id'),
            'last_activity' => ActivityLog::with('user')->latest()->first(),
        ];

        // Atividades recentes (últimas 10)
        $recentActivities = ActivityLog::with('user:id,name,email,avatar')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'user' => $activity->user ? [
                        'id' => $activity->user->id,
                        'name' => $activity->user->name,
                        'email' => $activity->user->email,
                        'avatar_url' => $activity->user->avatar_url,
                    ] : null,
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'created_at' => $activity->created_at,
                    'time_ago' => $activity->created_at->diffForHumans(),
                ];
            });

        // Dados para gráficos - Usuários criados nos últimos 7 dias
        $userChartData = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return [
                'date' => $date->format('d/m'),
                'users' => User::whereDate('created_at', $date)->count(),
            ];
        });

        // Dados para gráficos - Atividades dos últimos 7 dias
        $activityChartData = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return [
                'date' => $date->format('d/m'),
                'activities' => ActivityLog::whereDate('created_at', $date)->count(),
            ];
        });

        // Distribuição por tipos de ação
        $actionDistribution = ActivityLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'action' => $item->action,
                    'count' => $item->count,
                    'label' => $this->getActionLabel($item->action),
                ];
            });

        // Usuários mais ativos (últimos 30 dias)
        $topUsers = ActivityLog::where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as activity_count')
            ->groupBy('user_id')
            ->orderByDesc('activity_count')
            ->limit(5)
            ->with('user:id,name,email,avatar')
            ->get()
            ->map(function ($item) {
                return [
                    'user' => [
                        'id' => $item->user->id,
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                        'avatar_url' => $item->user->avatar_url,
                    ],
                    'activity_count' => $item->activity_count,
                ];
            });

        // Alertas e notificações
        $alerts = [];

        // Verificar se há muita atividade suspeita
        $suspiciousActivityCount = ActivityLog::where('action', 'access_denied')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($suspiciousActivityCount > 10) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Atividade Suspeita Detectada',
                'message' => "Foram registradas {$suspiciousActivityCount} tentativas de acesso negado nas últimas 24 horas.",
                'action_url' => route('activity-logs.index', ['action' => 'access_denied']),
                'action_text' => 'Ver Logs',
            ];
        }

        // Verificar se há novos usuários que precisam de verificação
        $unverifiedUsers = User::whereNull('email_verified_at')->count();
        if ($unverifiedUsers > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Usuários Não Verificados',
                'message' => "Há {$unverifiedUsers} usuários com email não verificado.",
                'action_url' => route('users.index'),
                'action_text' => 'Ver Usuários',
            ];
        }

        // Horários de pico de atividade (últimos 7 dias)
        $peakHours = ActivityLog::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(3)
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour . ':00',
                    'count' => $item->count,
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'activityStats' => $activityStats,
            'recentActivities' => $recentActivities,
            'userChartData' => $userChartData,
            'activityChartData' => $activityChartData,
            'actionDistribution' => $actionDistribution,
            'topUsers' => $topUsers,
            'alerts' => $alerts,
            'peakHours' => $peakHours,
        ]);
    }

    private function getActionLabel(string $action): string
    {
        $labels = [
            'login' => 'Login',
            'logout' => 'Logout',
            'create' => 'Criação',
            'update' => 'Atualização',
            'delete' => 'Exclusão',
            'access' => 'Acesso',
            'access_denied' => 'Acesso Negado',
        ];

        return $labels[$action] ?? ucfirst($action);
    }
}
