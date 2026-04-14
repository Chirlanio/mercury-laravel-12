<?php

namespace App\Http\Controllers;

use App\Models\HdDepartment;
use App\Services\HelpdeskReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpdeskReportController extends Controller
{
    public function __construct(private HelpdeskReportService $reportService) {}

    public function index(Request $request)
    {
        $filters = $request->only(['department_id', 'date_from', 'date_to']);

        return Inertia::render('Helpdesk/Reports', [
            'volumeByDay' => $this->reportService->volumeByDay($filters),
            'slaCompliance' => $this->reportService->slaCompliance($filters),
            'distributionByDepartment' => $this->reportService->distributionByDepartment($filters),
            'averageResolutionTime' => $this->reportService->averageResolutionTime($filters),
            'deflectionStats' => $this->reportService->deflectionStats($filters),
            'aiAccuracy' => $this->reportService->aiAccuracy($filters),
            'departments' => HdDepartment::active()->ordered()->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function volumeByDay(Request $request)
    {
        return response()->json($this->reportService->volumeByDay($request->all()));
    }

    public function slaCompliance(Request $request)
    {
        return response()->json($this->reportService->slaCompliance($request->all()));
    }
}
