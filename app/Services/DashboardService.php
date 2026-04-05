<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\OrderPayment;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\Transfer;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function getUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
            'new_users_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    public function getActivityStats(): array
    {
        return [
            'total_activities_today' => ActivityLog::whereDate('created_at', today())->count(),
            'total_activities_week' => ActivityLog::where('created_at', '>=', now()->startOfWeek())->count(),
            'unique_active_users_today' => ActivityLog::whereDate('created_at', today())
                ->distinct('user_id')->count('user_id'),
            'last_activity' => ActivityLog::with('user')->latest()->first(),
        ];
    }

    public function getRecentActivities(): \Illuminate\Support\Collection
    {
        return ActivityLog::with('user:id,name,email,avatar')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($activity) => [
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
            ]);
    }

    public function getUserChartData(): \Illuminate\Support\Collection
    {
        return collect(range(6, 0))->map(fn ($daysAgo) => [
            'date' => now()->subDays($daysAgo)->format('d/m'),
            'users' => User::whereDate('created_at', now()->subDays($daysAgo))->count(),
        ]);
    }

    public function getActivityChartData(): \Illuminate\Support\Collection
    {
        return collect(range(6, 0))->map(fn ($daysAgo) => [
            'date' => now()->subDays($daysAgo)->format('d/m'),
            'activities' => ActivityLog::whereDate('created_at', now()->subDays($daysAgo))->count(),
        ]);
    }

    public function getActionDistribution(): \Illuminate\Support\Collection
    {
        return ActivityLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => [
                'action' => $item->action,
                'count' => $item->count,
                'label' => $this->getActionLabel($item->action),
            ]);
    }

    public function getTopUsers(): \Illuminate\Support\Collection
    {
        return ActivityLog::where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as activity_count')
            ->groupBy('user_id')
            ->orderByDesc('activity_count')
            ->limit(5)
            ->with('user:id,name,email,avatar')
            ->get()
            ->map(fn ($item) => [
                'user' => [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'email' => $item->user->email,
                    'avatar_url' => $item->user->avatar_url,
                ],
                'activity_count' => $item->activity_count,
            ]);
    }

    public function getAlerts(): array
    {
        $alerts = [];

        $suspiciousCount = ActivityLog::where('action', 'access_denied')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($suspiciousCount > 10) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Atividade Suspeita Detectada',
                'message' => "Foram registradas {$suspiciousCount} tentativas de acesso negado nas últimas 24 horas.",
                'action_url' => route('activity-logs.index', ['action' => 'access_denied']),
                'action_text' => 'Ver Logs',
            ];
        }

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

        $overduePayments = OrderPayment::overdue()->count();
        if ($overduePayments > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Pagamentos Vencidos',
                'message' => "Há {$overduePayments} ordens de pagamento vencidas.",
                'action_url' => route('order-payments.index'),
                'action_text' => 'Ver Pagamentos',
            ];
        }

        $pendingAdjustments = StockAdjustment::active()->pending()->count();
        if ($pendingAdjustments > 5) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Ajustes de Estoque Pendentes',
                'message' => "Há {$pendingAdjustments} ajustes de estoque aguardando análise.",
                'action_url' => route('stock-adjustments.index'),
                'action_text' => 'Ver Ajustes',
            ];
        }

        return $alerts;
    }

    public function getPeakHours(): \Illuminate\Support\Collection
    {
        return ActivityLog::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(3)
            ->get()
            ->map(fn ($item) => [
                'hour' => $item->hour . ':00',
                'count' => $item->count,
            ]);
    }

    public function getSalesSummary(): array
    {
        $currentMonth = now();
        $lastMonth = now()->subMonth();

        $currentTotal = Sale::forMonth($currentMonth->month, $currentMonth->year)
            ->sum('total_sales');
        $lastTotal = Sale::forMonth($lastMonth->month, $lastMonth->year)
            ->sum('total_sales');

        $variation = $lastTotal > 0
            ? round((($currentTotal - $lastTotal) / $lastTotal) * 100, 1)
            : 0;

        return [
            'current_month_total' => round($currentTotal, 2),
            'last_month_total' => round($lastTotal, 2),
            'variation_pct' => $variation,
            'active_stores' => Sale::forMonth($currentMonth->month, $currentMonth->year)
                ->distinct('store_id')->count('store_id'),
            'active_employees' => Sale::forMonth($currentMonth->month, $currentMonth->year)
                ->distinct('employee_id')->count('employee_id'),
        ];
    }

    public function getSalesChartData(): \Illuminate\Support\Collection
    {
        return collect(range(6, 0))->map(fn ($daysAgo) => [
            'date' => now()->subDays($daysAgo)->format('d/m'),
            'value' => (float) Sale::whereDate('date_sales', now()->subDays($daysAgo))->sum('total_sales'),
        ]);
    }

    public function getUsersOnlineSummary(): array
    {
        UserSession::markInactiveSessions();

        $onlineCount = UserSession::online()->count();

        return [
            'online_count' => $onlineCount,
        ];
    }

    public function getTransfersSummary(): array
    {
        $now = now();

        return [
            'pending_count' => Transfer::pending()->count(),
            'in_transit_count' => Transfer::inTransit()->count(),
            'completed_this_month' => Transfer::where('status', 'confirmed')
                ->forMonth($now->month, $now->year)->count(),
        ];
    }

    public function getStockAdjustmentsSummary(): array
    {
        $now = now();

        return [
            'pending_count' => StockAdjustment::active()->pending()->count(),
            'under_analysis_count' => StockAdjustment::active()
                ->where('status', 'under_analysis')->count(),
            'adjusted_this_month' => StockAdjustment::active()
                ->where('status', 'adjusted')
                ->forMonth($now->month, $now->year)->count(),
        ];
    }

    public function getOrderPaymentsSummary(): array
    {
        $byStatus = [];
        foreach (OrderPayment::STATUS_LABELS as $status => $label) {
            $query = OrderPayment::active()->forStatus($status);
            $byStatus[$status] = [
                'count' => $query->count(),
                'total' => round($query->sum('total_value'), 2),
            ];
        }

        return [
            'by_status' => $byStatus,
            'overdue_count' => OrderPayment::overdue()->count(),
            'total_pending_amount' => round(
                OrderPayment::active()->where('status', '!=', 'done')->sum('total_value'),
                2
            ),
        ];
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
