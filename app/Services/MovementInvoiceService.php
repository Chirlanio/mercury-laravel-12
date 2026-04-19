<?php

namespace App\Services;

use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Store;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reúne todos os itens (movements) de uma nota fiscal identificada por
 * (store_code, invoice_number) e gera visualização, export XLSX e PDF.
 *
 * NF não é entidade primária em movements — é uma chave composta. O serviço
 * consolida os movimentos em um documento consultável e imprimível.
 */
class MovementInvoiceService
{
    /**
     * Busca todos os movements de uma NF. Retorna null se não encontrar nada.
     * NF é chave composta (store_code + invoice_number + movement_date) porque
     * número de cupom/NF reseta por ano e pode repetir entre lojas.
     *
     * @return array{header: array, items: Collection, totals: array}|null
     */
    public function find(string $storeCode, string $invoiceNumber, string $movementDate): ?array
    {
        $items = Movement::query()
            ->where('store_code', $storeCode)
            ->where('invoice_number', $invoiceNumber)
            ->where('movement_date', $movementDate)
            ->orderBy('movement_time')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        $first = $items->first();
        $store = Store::where('code', $storeCode)->first();
        $typeCodes = $items->pluck('movement_code')->unique()->values();
        $types = MovementType::whereIn('code', $typeCodes)->pluck('description', 'code');

        $header = [
            'store_code' => $storeCode,
            'store_name' => $store?->display_name ?? $store?->name,
            'invoice_number' => $invoiceNumber,
            'movement_date' => $first->movement_date?->format('Y-m-d'),
            'movement_time' => $first->movement_time ? substr($first->movement_time, 0, 8) : null,
            'cpf_customer' => $first->cpf_customer,
            'cpf_consultant' => $first->cpf_consultant,
            'synced_at' => $first->synced_at,
        ];

        $totals = [
            'items' => $items->count(),
            'quantity' => (float) $items->sum('quantity'),
            'net_quantity' => (float) $items->sum('net_quantity'),
            'realized_value' => (float) $items->sum('realized_value'),
            'net_value' => (float) $items->sum('net_value'),
            'discount_value' => (float) $items->sum('discount_value'),
            'cost_value' => (float) $items->sum(fn ($m) => (float) $m->cost_price * (float) $m->quantity),
        ];

        $mapped = $items->map(function ($m) use ($types) {
            return [
                'id' => $m->id,
                'movement_date' => $m->movement_date?->format('d/m/Y'),
                'movement_time' => $m->movement_time ? substr($m->movement_time, 0, 8) : null,
                'movement_code' => $m->movement_code,
                'movement_type' => $types[$m->movement_code] ?? (string) $m->movement_code,
                'entry_exit' => $m->entry_exit,
                'ref_size' => $m->ref_size,
                'barcode' => $m->barcode,
                'quantity' => (float) $m->quantity,
                'net_quantity' => (float) $m->net_quantity,
                'sale_price' => (float) $m->sale_price,
                'cost_price' => (float) $m->cost_price,
                'realized_value' => (float) $m->realized_value,
                'discount_value' => (float) $m->discount_value,
                'net_value' => (float) $m->net_value,
            ];
        });

        return [
            'header' => $header,
            'items' => $mapped,
            'totals' => $totals,
            'raw_items' => $items,
        ];
    }

    public function exportXlsx(string $storeCode, string $invoiceNumber, string $movementDate): BinaryFileResponse
    {
        $data = $this->find($storeCode, $invoiceNumber, $movementDate);

        if (! $data) {
            abort(404, 'Nota fiscal não encontrada.');
        }

        $rows = collect($data['items']);
        $header = $data['header'];
        $totals = $data['totals'];

        $export = new class($rows, $header, $totals) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(public Collection $rows, public array $header, public array $totals) {}

            public function collection()
            {
                $headerRow = (object) [
                    '__is_header' => true,
                    'label' => sprintf(
                        'NF %s · Loja %s%s · Data %s · Cliente %s · Consultor %s',
                        $this->header['invoice_number'],
                        $this->header['store_code'],
                        $this->header['store_name'] ? ' ('.$this->header['store_name'].')' : '',
                        $this->header['movement_date']
                            ? \Carbon\Carbon::parse($this->header['movement_date'])->format('d/m/Y')
                            : '-',
                        $this->header['cpf_customer'] ?: '-',
                        $this->header['cpf_consultant'] ?: '-',
                    ),
                ];

                $totalsRow = (object) [
                    '__is_totals' => true,
                    'quantity' => $this->totals['quantity'],
                    'realized_value' => $this->totals['realized_value'],
                    'discount_value' => $this->totals['discount_value'],
                    'net_value' => $this->totals['net_value'],
                ];

                return collect([$headerRow])
                    ->concat($this->rows)
                    ->push($totalsRow);
            }

            public function headings(): array
            {
                return [
                    'ID', 'Data', 'Hora', 'Tipo', 'E/S', 'Ref/Tam', 'Barcode',
                    'Qtde', 'Qtde Líq.', 'Preço Venda', 'Preço Custo',
                    'Vlr. Realizado', 'Desconto', 'Vlr. Líquido',
                ];
            }

            public function map($r): array
            {
                if (is_object($r) && isset($r->__is_header)) {
                    return [$r->label];
                }

                if (is_object($r) && isset($r->__is_totals)) {
                    return [
                        '', '', '', '', '', '', 'TOTAIS',
                        number_format($r->quantity, 3, ',', '.'),
                        '',
                        '',
                        '',
                        number_format($r->realized_value, 2, ',', '.'),
                        number_format($r->discount_value, 2, ',', '.'),
                        number_format($r->net_value, 2, ',', '.'),
                    ];
                }

                $item = (array) $r;

                return [
                    $item['id'],
                    $item['movement_date'],
                    $item['movement_time'] ?? '-',
                    $item['movement_type'],
                    $item['entry_exit'] === 'E' ? 'Entrada' : 'Saída',
                    $item['ref_size'] ?? '-',
                    $item['barcode'] ?? '-',
                    number_format($item['quantity'], 3, ',', '.'),
                    number_format($item['net_quantity'], 3, ',', '.'),
                    number_format($item['sale_price'], 2, ',', '.'),
                    number_format($item['cost_price'], 2, ',', '.'),
                    number_format($item['realized_value'], 2, ',', '.'),
                    number_format($item['discount_value'], 2, ',', '.'),
                    number_format($item['net_value'], 2, ',', '.'),
                ];
            }
        };

        $filename = sprintf(
            'nota-fiscal-%s-%s-%s-%s.xlsx',
            preg_replace('/[^A-Za-z0-9_-]/', '', $storeCode),
            preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceNumber),
            $movementDate,
            now()->format('Y-m-d-His')
        );

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    public function exportPdf(string $storeCode, string $invoiceNumber, string $movementDate): Response
    {
        $data = $this->find($storeCode, $invoiceNumber, $movementDate);

        if (! $data) {
            abort(404, 'Nota fiscal não encontrada.');
        }

        $pdf = Pdf::loadView('pdf.movement-invoice', [
            'header' => $data['header'],
            'items' => $data['items'],
            'totals' => $data['totals'],
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'nota-fiscal-%s-%s-%s-%s.pdf',
            preg_replace('/[^A-Za-z0-9_-]/', '', $storeCode),
            preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceNumber),
            $movementDate,
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }
}
