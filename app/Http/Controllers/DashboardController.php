<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Services\DashboardService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    public function index()
    {
        $user = auth()->user();

        $data = [
            'stats' => $this->dashboardService->getUserStats(),
            'activityStats' => $this->dashboardService->getActivityStats(),
            'recentActivities' => $this->dashboardService->getRecentActivities(),
            'userChartData' => $this->dashboardService->getUserChartData(),
            'activityChartData' => $this->dashboardService->getActivityChartData(),
            'actionDistribution' => $this->dashboardService->getActionDistribution(),
            'topUsers' => $this->dashboardService->getTopUsers(),
            'alerts' => $this->dashboardService->getAlerts(),
            'peakHours' => $this->dashboardService->getPeakHours(),
            'usersOnlineSummary' => $this->dashboardService->getUsersOnlineSummary(),
        ];

        if ($user->role->hasPermissionTo(Permission::VIEW_SALES)) {
            $data['salesSummary'] = $this->dashboardService->getSalesSummary();
            $data['salesChartData'] = $this->dashboardService->getSalesChartData();
        }

        if ($user->role->hasPermissionTo(Permission::VIEW_TRANSFERS)) {
            $data['transfersSummary'] = $this->dashboardService->getTransfersSummary();
        }

        if ($user->role->hasPermissionTo(Permission::VIEW_ADJUSTMENTS)) {
            $data['stockSummary'] = $this->dashboardService->getStockAdjustmentsSummary();
        }

        if ($user->role->hasPermissionTo(Permission::VIEW_ORDER_PAYMENTS)) {
            $data['paymentsSummary'] = $this->dashboardService->getOrderPaymentsSummary();
        }

        return Inertia::render('Dashboard', $data);
    }
}
