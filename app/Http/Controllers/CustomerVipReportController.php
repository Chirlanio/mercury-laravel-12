<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerVipReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Relatório YoY de faturamento do cliente VIP.
 *
 * GET /customers/{customer}/vip/report/yoy?year=2026&mode=ytd|full_year
 *
 * Retorna JSON consumido pelo gráfico recharts na UI.
 */
class CustomerVipReportController extends Controller
{
    public function __construct(private readonly CustomerVipReportService $reports) {}

    public function yoy(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'mode' => ['nullable', 'in:ytd,full_year'],
        ]);

        $year = (int) ($data['year'] ?? now()->year);
        $mode = $data['mode'] ?? 'ytd';

        $payload = $this->reports->yearOverYear($customer, $year, $mode);

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'formatted_cpf' => $customer->formatted_cpf,
            ],
            'report' => $payload,
        ]);
    }
}
