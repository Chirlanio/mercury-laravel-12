<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Devolução #{{ $order->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; margin: 20px; }
        h1 { font-size: 18px; color: #4338ca; margin: 0 0 4px; }
        .subtitle { font-size: 10px; color: #6b7280; margin-bottom: 12px; }
        .header { border-bottom: 2px solid #4338ca; padding-bottom: 10px; margin-bottom: 15px; }
        .section { margin-bottom: 14px; page-break-inside: avoid; }
        .section-title {
            font-size: 10px; font-weight: bold; color: #4338ca;
            text-transform: uppercase; border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px; margin-bottom: 6px;
        }
        .grid { display: table; width: 100%; }
        .grid-row { display: table-row; }
        .grid-cell {
            display: table-cell; padding: 4px 8px 4px 0;
            width: 50%; vertical-align: top;
        }
        .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .value { font-size: 11px; color: #111; font-weight: bold; }
        .value-money { font-size: 13px; color: #4338ca; font-weight: bold; }
        .highlight {
            background: #eef2ff; border: 1px solid #c7d2fe;
            border-radius: 4px; padding: 10px; margin: 8px 0;
        }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th {
            background: #4338ca; color: #fff; padding: 5px;
            font-size: 8px; text-align: left; text-transform: uppercase;
        }
        table.items td { padding: 4px 5px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        table.items tr:nth-child(even) { background: #f9fafb; }
        .right { text-align: right; }
        .badge {
            display: inline-block; padding: 2px 8px; font-size: 9px;
            border-radius: 10px; font-weight: bold;
        }
        .b-pending { background: #fef3c7; color: #92400e; }
        .b-approved { background: #dbeafe; color: #1e40af; }
        .b-awaiting_product { background: #ffedd5; color: #9a3412; }
        .b-processing { background: #ede9fe; color: #5b21b6; }
        .b-completed { background: #dcfce7; color: #166534; }
        .b-cancelled { background: #fee2e2; color: #991b1b; }
        .timeline { margin-top: 6px; }
        .timeline-item {
            padding: 4px 0 4px 12px; border-left: 2px solid #c7d2fe;
            margin-bottom: 4px; font-size: 9px;
        }
        .timeline-title { font-weight: bold; color: #4338ca; }
        .timeline-meta { color: #6b7280; font-size: 8px; }
        .footer {
            margin-top: 20px; font-size: 8px; color: #9ca3af;
            text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Comprovante de Devolução #{{ $order->id }}</h1>
        <div class="subtitle">
            Gerado em {{ $generatedAt->format('d/m/Y H:i') }} · Grupo Meia Sola — Mercury
        </div>
    </div>

    <div class="section">
        <div class="section-title">Status atual</div>
        <span class="badge b-{{ $order->status?->value }}">{{ $order->status?->label() }}</span>
        <span style="margin-left: 10px; font-size: 9px; color: #6b7280;">
            {{ $order->type?->label() }}
            · {{ $order->reason_category?->label() }}
        </span>
    </div>

    <div class="section">
        <div class="section-title">Dados da venda</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">NF / Cupom fiscal</div>
                    <div class="value">{{ $order->invoice_number }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Loja</div>
                    <div class="value">{{ $order->store_code }}{{ $order->store?->name ? ' — '.$order->store->name : '' }}</div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Data da venda</div>
                    <div class="value">{{ optional($order->movement_date)->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Total da NF</div>
                    <div class="value">R$ {{ number_format((float) $order->sale_total, 2, ',', '.') }}</div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Cliente</div>
                    <div class="value">{{ $order->customer_name }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">CPF Cliente</div>
                    <div class="value">{{ $order->cpf_customer ?? '—' }}</div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Motivo</div>
                    <div class="value">{{ $order->reason?->name ?? $order->reason_category?->label() }}</div>
                </div>
                <div class="grid-cell">
                    @if ($order->reverse_tracking_code)
                        <div class="label">Código de rastreio</div>
                        <div class="value">{{ $order->reverse_tracking_code }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Valores</div>
        <div class="highlight">
            <div class="label">Valor dos itens devolvidos</div>
            <div class="value-money">R$ {{ number_format((float) $order->amount_items, 2, ',', '.') }}</div>
            @if ($order->refund_amount !== null)
                <div style="margin-top: 6px;">
                    <div class="label">Valor a reembolsar</div>
                    <div class="value-money">R$ {{ number_format((float) $order->refund_amount, 2, ',', '.') }}</div>
                </div>
            @endif
        </div>
    </div>

    @if ($order->items && $order->items->count() > 0)
        <div class="section">
            <div class="section-title">Itens devolvidos</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Ref/Tam</th>
                        <th class="right">Qtd</th>
                        <th class="right">Unitário</th>
                        <th class="right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->barcode ?? '—' }}</td>
                            <td>{{ trim(($item->reference ?? '').' · '.($item->size ?? ''), ' ·') ?: '—' }}</td>
                            <td class="right">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                            <td class="right">R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                            <td class="right">R$ {{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($order->notes)
        <div class="section">
            <div class="section-title">Observações</div>
            <div style="font-size: 10px; white-space: pre-wrap;">{{ $order->notes }}</div>
        </div>
    @endif

    @if ($order->cancelled_reason)
        <div class="section">
            <div class="section-title">Motivo do cancelamento</div>
            <div style="font-size: 10px; white-space: pre-wrap; background: #fee2e2; padding: 8px; border-radius: 4px;">
                {{ $order->cancelled_reason }}
            </div>
        </div>
    @endif

    @if ($order->statusHistory && $order->statusHistory->count() > 0)
        <div class="section">
            <div class="section-title">Histórico</div>
            <div class="timeline">
                @foreach ($order->statusHistory->sortBy('created_at') as $h)
                    <div class="timeline-item">
                        <div class="timeline-title">
                            @if ($h->from_status)
                                {{ $h->from_status->label() }} → {{ $h->to_status->label() }}
                            @else
                                {{ $h->to_status->label() }}
                            @endif
                        </div>
                        <div class="timeline-meta">
                            {{ $h->changedBy?->name ?? 'Sistema' }}
                            — {{ optional($h->created_at)->format('d/m/Y H:i') }}
                            @if ($h->note)
                                · {{ $h->note }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="footer">
        Devolução #{{ $order->id }} · NF {{ $order->invoice_number }} · Loja {{ $order->store_code }}
        · Comprovante gerado automaticamente pelo sistema Mercury.
    </div>
</body>
</html>
