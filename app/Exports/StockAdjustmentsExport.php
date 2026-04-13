<?php

namespace App\Exports;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta ajustes de estoque no formato "uma linha por item", que é o formato
 * mais útil para Financeiro auditar cada referência individualmente.
 */
class StockAdjustmentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(
        protected User $user,
        protected array $filters = [],
    ) {
    }

    public function query()
    {
        $query = StockAdjustmentItem::query()
            ->with([
                'stockAdjustment.store',
                'stockAdjustment.employee',
                'stockAdjustment.createdBy',
                'reason',
            ])
            ->whereHas('stockAdjustment', function ($q) {
                $q->whereNull('deleted_at');

                // Escopo por loja para não-admin
                if (! $this->isAdmin($this->user) && ! empty($this->user->store_id)) {
                    $q->whereHas('store', fn ($sq) => $sq->where('code', $this->user->store_id));
                }

                if (! empty($this->filters['status'])) {
                    $q->where('status', $this->filters['status']);
                }
                if (! empty($this->filters['store_id'])) {
                    $q->where('store_id', (int) $this->filters['store_id']);
                }
                if (! empty($this->filters['date_from'])) {
                    $q->whereDate('created_at', '>=', $this->filters['date_from']);
                }
                if (! empty($this->filters['date_to'])) {
                    $q->whereDate('created_at', '<=', $this->filters['date_to']);
                }
            });

        if (! empty($this->filters['reason_id'])) {
            $query->where('reason_id', (int) $this->filters['reason_id']);
        }
        if (! empty($this->filters['direction'])) {
            $query->where('direction', $this->filters['direction']);
        }

        return $query->orderByDesc('stock_adjustment_id');
    }

    public function headings(): array
    {
        return [
            'Ajuste #',
            'Status',
            'Loja',
            'Consultora',
            'Cliente',
            'Criado por',
            'Data',
            'Referência',
            'Tamanho',
            'Direção',
            'Qtde',
            'Qtde c/ sinal',
            'Estoque Atual',
            'Motivo',
            'Código Motivo',
            'Observação do Item',
            'Observação Geral',
        ];
    }

    public function map($item): array
    {
        $adjustment = $item->stockAdjustment;

        return [
            $adjustment?->id,
            $adjustment ? (StockAdjustment::STATUS_LABELS[$adjustment->status] ?? $adjustment->status) : '-',
            $adjustment?->store ? ($adjustment->store->code.' - '.$adjustment->store->name) : '-',
            $adjustment?->employee?->name ?? '-',
            $adjustment?->client_name ?? '-',
            $adjustment?->createdBy?->name ?? '-',
            $adjustment?->created_at?->format('d/m/Y H:i') ?? '-',
            $item->reference,
            $item->size ?? '-',
            $item->direction === 'increase' ? 'Inclusão (+)' : 'Remoção (-)',
            $item->quantity,
            $item->signed_quantity,
            $item->current_stock ?? '-',
            $item->reason?->name ?? '-',
            $item->reason?->code ?? '-',
            $item->notes ?? '-',
            $adjustment?->observation ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role?->value ?? null, ['super_admin', 'admin', 'support'], true);
    }
}
