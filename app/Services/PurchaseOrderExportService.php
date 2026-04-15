<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporta ordens de compra para Excel ou PDF.
 *
 * Excel: sem limite de linhas (usa maatwebsite/excel streaming).
 * PDF: limitado a 1000 ordens (controle de memória do dompdf — mesma
 * regra que existe no v1).
 */
class PurchaseOrderExportService
{
    public const PDF_MAX_ORDERS = 1000;

    /**
     * @param  Builder<PurchaseOrder>  $query  Query JÁ filtrada/escopada pelo controller
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $orders = $query
            ->with(['supplier', 'store', 'brand', 'items'])
            ->get();

        $export = new class($orders) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(public $rows) {}
            public function collection() { return $this->rows; }
            public function headings(): array {
                return [
                    'Nº Ordem', 'Descrição', 'Estação', 'Coleção', 'Lançamento',
                    'Fornecedor', 'Loja', 'Marca',
                    'Data Pedido', 'Previsão', 'Entregue em', 'Status',
                    'Itens', 'Unidades', 'Custo Total', 'Venda Total',
                ];
            }
            public function map($order): array {
                return [
                    $order->order_number,
                    $order->short_description,
                    $order->season,
                    $order->collection,
                    $order->release_name,
                    $order->supplier?->nome_fantasia,
                    $order->store?->name ?? $order->store_id,
                    $order->brand?->name,
                    $order->order_date?->format('d/m/Y'),
                    $order->predict_date?->format('d/m/Y'),
                    $order->delivered_at?->format('d/m/Y H:i'),
                    $order->status?->label(),
                    $order->items->count(),
                    $order->total_units,
                    number_format($order->total_cost, 2, ',', '.'),
                    number_format($order->total_selling, 2, ',', '.'),
                ];
            }
        };

        $filename = 'ordens-compra-' . now()->format('Y-m-d-His') . '.xlsx';
        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     *
     * @throws \RuntimeException quando excede PDF_MAX_ORDERS
     */
    public function exportPdf(Builder $query): Response
    {
        $count = (clone $query)->count();
        if ($count > self::PDF_MAX_ORDERS) {
            throw new \RuntimeException(
                "Export PDF limitado a " . self::PDF_MAX_ORDERS . " ordens. Refine os filtros ou use Excel."
            );
        }

        $orders = $query
            ->with(['supplier', 'store', 'brand', 'items'])
            ->get();

        $totalCost = $orders->sum(fn ($o) => $o->total_cost);
        $totalSelling = $orders->sum(fn ($o) => $o->total_selling);
        $totalUnits = $orders->sum(fn ($o) => $o->total_units);

        $pdf = Pdf::loadView('pdf.purchase-orders', [
            'orders' => $orders,
            'totalCost' => $totalCost,
            'totalSelling' => $totalSelling,
            'totalUnits' => $totalUnits,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('ordens-compra-' . now()->format('Y-m-d-His') . '.pdf');
    }
}
