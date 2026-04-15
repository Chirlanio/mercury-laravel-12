<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Ordens de Compra</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; color: #333; margin: 15px; }
        h1 { font-size: 16px; color: #4338ca; margin: 0 0 4px; }
        .header { border-bottom: 2px solid #4338ca; padding-bottom: 8px; margin-bottom: 12px; }
        .header-meta { font-size: 9px; color: #6b7280; }
        .summary { display: table; width: 100%; margin: 8px 0 12px; }
        .summary-item { display: table-cell; padding: 6px; border: 1px solid #e5e7eb; text-align: center; }
        .summary-label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .summary-value { font-size: 14px; font-weight: bold; color: #4338ca; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #4338ca; color: #fff; padding: 5px; font-size: 8px; text-align: left; text-transform: uppercase; }
        td { padding: 4px 5px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) { background: #f9fafb; }
        .right { text-align: right; }
        .center { text-align: center; }
        .footer { margin-top: 15px; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 6px; }
        .badge { display: inline-block; padding: 1px 6px; font-size: 8px; border-radius: 8px; font-weight: bold; }
        .b-pending { background: #fef3c7; color: #92400e; }
        .b-invoiced { background: #dbeafe; color: #1e40af; }
        .b-partial { background: #ede9fe; color: #5b21b6; }
        .b-delivered { background: #dcfce7; color: #166534; }
        .b-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de Ordens de Compra</h1>
        <div class="header-meta">
            Gerado em {{ $generatedAt->format('d/m/Y H:i') }} · {{ $orders->count() }} ordem(ns) · Grupo Meia Sola — Mercury
        </div>
    </div>

    <div class="summary">
        <div class="summary-item">
            <div class="summary-label">Total de Ordens</div>
            <div class="summary-value">{{ $orders->count() }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Unidades</div>
            <div class="summary-value">{{ number_format($totalUnits, 0, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Custo Total</div>
            <div class="summary-value">R$ {{ number_format($totalCost, 2, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Venda Total</div>
            <div class="summary-value">R$ {{ number_format($totalSelling, 2, ',', '.') }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Descrição</th>
                <th>Estação / Coleção</th>
                <th>Fornecedor</th>
                <th>Loja</th>
                <th class="center">Itens</th>
                <th class="center">Unid.</th>
                <th class="right">Custo</th>
                <th class="right">Venda</th>
                <th>Pedido</th>
                <th>Previsão</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $o)
                <tr>
                    <td><strong>{{ $o->order_number }}</strong></td>
                    <td>{{ $o->short_description ?: '—' }}</td>
                    <td>{{ $o->season }} / {{ $o->collection }}</td>
                    <td>{{ $o->supplier?->nome_fantasia ?: '—' }}</td>
                    <td>{{ $o->store?->name ?: $o->store_id }}</td>
                    <td class="center">{{ $o->items->count() }}</td>
                    <td class="center">{{ $o->total_units }}</td>
                    <td class="right">{{ number_format($o->total_cost, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($o->total_selling, 2, ',', '.') }}</td>
                    <td>{{ $o->order_date?->format('d/m/Y') }}</td>
                    <td>{{ $o->predict_date?->format('d/m/Y') ?: '—' }}</td>
                    <td>
                        @php
                            $color = match($o->status?->value) {
                                'pending' => 'b-pending', 'invoiced' => 'b-invoiced',
                                'partial_invoiced' => 'b-partial', 'delivered' => 'b-delivered',
                                'cancelled' => 'b-cancelled', default => '',
                            };
                        @endphp
                        <span class="badge {{ $color }}">{{ $o->status?->label() }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Mercury — Grupo Meia Sola · Relatório de Ordens de Compra · {{ $generatedAt->format('d/m/Y H:i') }}
    </div>
</body>
</html>
