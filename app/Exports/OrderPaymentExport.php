<?php

namespace App\Exports;

use App\Models\OrderPayment;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrderPaymentExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = OrderPayment::query()
            ->with(['store:id,code,name', 'supplier:id,nome_fantasia', 'manager:id,name', 'createdBy:id,name'])
            ->active()
            ->latest('date_payment');

        if (! empty($this->filters['status'])) {
            $query->forStatus($this->filters['status']);
        }

        if (! empty($this->filters['store_id'])) {
            $query->forStore($this->filters['store_id']);
        }

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('number_nf', 'like', "%{$search}%")
                    ->orWhere('launch_number', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['date_from'])) {
            $query->where('date_payment', '>=', $this->filters['date_from']);
        }

        if (! empty($this->filters['date_to'])) {
            $query->where('date_payment', '<=', $this->filters['date_to']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fornecedor',
            'Loja',
            'Descrição',
            'Valor Total',
            'Data Pagamento',
            'Data Pago',
            'Nº NF',
            'Nº Lançamento',
            'Tipo Pagamento',
            'Status',
            'Parcelas',
            'Adiantamento',
            'Valor Adiantamento',
            'Rateio',
            'Criado por',
            'Data Criação',
        ];
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->supplier?->nome_fantasia ?? '-',
            $order->store ? "{$order->store->code} - {$order->store->name}" : '-',
            $order->description,
            number_format($order->total_value, 2, ',', '.'),
            $order->date_payment?->format('d/m/Y'),
            $order->date_paid?->format('d/m/Y'),
            $order->number_nf,
            $order->launch_number,
            $order->payment_type,
            $order->status_label,
            $order->installments,
            $order->advance ? 'Sim' : 'Não',
            $order->advance_amount ? number_format($order->advance_amount, 2, ',', '.') : '-',
            $order->has_allocation ? 'Sim' : 'Não',
            $order->createdBy?->name ?? '-',
            $order->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
