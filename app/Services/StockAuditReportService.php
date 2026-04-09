<?php

namespace App\Services;

use App\Models\StockAudit;
use App\Models\StockAuditAccuracyHistory;
use Barryvdh\DomPDF\Facade\Pdf;

class StockAuditReportService
{
    /**
     * Generate a PDF report for the audit.
     */
    public function generatePdf(StockAudit $audit): \Barryvdh\DomPDF\PDF
    {
        $data = $this->getReportData($audit);

        return Pdf::loadView('pdf.stock-audit-report', $data)
            ->setPaper('a4', 'landscape');
    }

    /**
     * Compile all report data for the audit.
     */
    public function getReportData(StockAudit $audit): array
    {
        $audit->load([
            'store',
            'vendor',
            'managerResponsible',
            'stockist',
            'createdBy',
            'authorizedBy',
            'teams.user',
            'teams.vendor',
            'signatures.signerUser',
        ]);

        $items = $audit->items()
            ->whereNotNull('accepted_count')
            ->orderBy('divergence')
            ->get();

        $divergentItems = $items->where('divergence', '!=', 0);
        $topLosses = $divergentItems->where('divergence', '<', 0)->sortBy('divergence')->take(10);
        $topSurpluses = $divergentItems->where('divergence', '>', 0)->sortByDesc('divergence')->take(10);

        $totalItems = $items->count();
        $totalDivergences = $divergentItems->count();
        $justifiedItems = $items->where('is_justified', true)->count();
        $storeJustifiedItems = $items->where('store_justified', true)->count();

        return [
            'audit' => $audit,
            'items' => $items,
            'divergentItems' => $divergentItems,
            'topLosses' => $topLosses,
            'topSurpluses' => $topSurpluses,
            'summary' => [
                'total_items' => $totalItems,
                'total_divergences' => $totalDivergences,
                'accuracy' => $audit->accuracy_percentage,
                'financial_loss' => $audit->financial_loss,
                'financial_surplus' => $audit->financial_surplus,
                'financial_loss_cost' => $audit->financial_loss_cost,
                'financial_surplus_cost' => $audit->financial_surplus_cost,
                'justified_items' => $justifiedItems,
                'store_justified_items' => $storeJustifiedItems,
            ],
            'timeline' => [
                'created_at' => $audit->created_at,
                'authorized_at' => $audit->authorized_at,
                'started_at' => $audit->started_at,
                'finished_at' => $audit->finished_at,
            ],
        ];
    }

    /**
     * Get accuracy history for a store (for charts).
     */
    public function getAccuracyHistory(?int $storeId = null, int $limit = 20): array
    {
        $query = StockAuditAccuracyHistory::with('store')
            ->orderByDesc('audit_date')
            ->limit($limit);

        if ($storeId) {
            $query->forStore($storeId);
        }

        return $query->get()->map(fn ($h) => [
            'id' => $h->id,
            'store_name' => $h->store->name ?? '',
            'accuracy' => (float) $h->accuracy_percentage,
            'total_items' => $h->total_items,
            'total_divergences' => $h->total_divergences,
            'financial_loss' => (float) $h->financial_loss,
            'financial_surplus' => (float) $h->financial_surplus,
            'audit_type' => $h->audit_type,
            'audit_date' => $h->audit_date->format('d/m/Y'),
        ])->toArray();
    }
}
