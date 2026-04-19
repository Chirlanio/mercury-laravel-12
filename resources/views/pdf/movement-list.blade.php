<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Movimentações Diárias</title>
    <style>
        @page { margin: 15mm 10mm; }
        body { font-family: Arial, sans-serif; font-size: 9px; color: #333; margin: 0; }
        h1 { font-size: 16px; color: #4338ca; margin: 0 0 3px; }
        .subtitle { font-size: 9px; color: #6b7280; margin-bottom: 10px; }
        .header { border-bottom: 2px solid #4338ca; padding-bottom: 8px; margin-bottom: 12px; }
        .filters {
            background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 4px;
            padding: 6px 8px; margin-bottom: 10px; font-size: 8px;
        }
        .filters strong { color: #4338ca; text-transform: uppercase; font-size: 7px; }
        .chip {
            display: inline-block; background: #fff; border: 1px solid #c7d2fe;
            border-radius: 10px; padding: 1px 8px; margin: 0 2px;
            font-size: 8px; color: #4338ca;
        }
        .totals {
            display: table; width: 100%; margin-bottom: 10px;
            background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px;
        }
        .totals-row { display: table-row; }
        .totals-cell {
            display: table-cell; padding: 6px 10px; width: 20%; text-align: center;
            border-right: 1px solid #e5e7eb;
        }
        .totals-cell:last-child { border-right: none; }
        .totals-cell .label { font-size: 7px; color: #6b7280; text-transform: uppercase; }
        .totals-cell .value { font-size: 12px; color: #4338ca; font-weight: bold; margin-top: 2px; }
        .neg { color: #b91c1c; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th {
            background: #4338ca; color: #fff; padding: 4px;
            font-size: 7px; text-align: left; text-transform: uppercase;
        }
        table.items td { padding: 3px 4px; border-bottom: 1px solid #f3f4f6; font-size: 8px; }
        table.items tr:nth-child(even) { background: #fafafa; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #9ca3af; }
        .footer {
            margin-top: 12px; font-size: 7px; color: #9ca3af; text-align: center;
            border-top: 1px solid #e5e7eb; padding-top: 6px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Movimentações Diárias</h1>
        <div class="subtitle">
            Gerado em {{ $generatedAt->format('d/m/Y H:i') }} · Grupo Meia Sola — Mercury
            · {{ number_format($totals['items'], 0, ',', '.') }}
            {{ $totals['items'] === 1 ? 'registro' : 'registros' }}
        </div>
    </div>

    <div class="filters">
        <strong>Filtros:</strong>
        <span class="chip">
            Período:
            {{ ! empty($filters['date_start']) ? \Carbon\Carbon::parse($filters['date_start'])->format('d/m/Y') : '-' }}
            a
            {{ ! empty($filters['date_end']) ? \Carbon\Carbon::parse($filters['date_end'])->format('d/m/Y') : '-' }}
        </span>
        @if ($storeLabel)
            <span class="chip">Loja: {{ $storeLabel }}</span>
        @endif
        @if ($typeLabel)
            <span class="chip">Tipo: {{ $typeLabel }}</span>
        @endif
        @if (! empty($filters['entry_exit']))
            <span class="chip">{{ $filters['entry_exit'] === 'E' ? 'Entrada' : 'Saída' }}</span>
        @endif
        @if (! empty($filters['cpf_consultant']))
            <span class="chip">Consultor: {{ $filters['cpf_consultant'] }}</span>
        @endif
        @if (! empty($filters['cpf_customer']))
            <span class="chip">Cliente: {{ $filters['cpf_customer'] }}</span>
        @endif
        @if (! empty($filters['sync_status']))
            <span class="chip">
                {{ $filters['sync_status'] === 'synced' ? 'Sincronizados' : 'Pendentes' }}
            </span>
        @endif
        @if (! empty($filters['search']))
            <span class="chip">Busca: "{{ $filters['search'] }}"</span>
        @endif
    </div>

    <div class="totals">
        <div class="totals-row">
            <div class="totals-cell">
                <div class="label">Registros</div>
                <div class="value">{{ number_format($totals['items'], 0, ',', '.') }}</div>
            </div>
            <div class="totals-cell">
                <div class="label">Qtde Total</div>
                <div class="value">{{ number_format($totals['quantity'], 3, ',', '.') }}</div>
            </div>
            <div class="totals-cell">
                <div class="label">Vlr. Realizado</div>
                <div class="value">R$ {{ number_format($totals['realized_value'], 2, ',', '.') }}</div>
            </div>
            <div class="totals-cell">
                <div class="label">Descontos</div>
                <div class="value">R$ {{ number_format($totals['discount_value'], 2, ',', '.') }}</div>
            </div>
            <div class="totals-cell">
                <div class="label">Vlr. Líquido</div>
                <div class="value {{ $totals['net_value'] < 0 ? 'neg' : '' }}">
                    R$ {{ number_format($totals['net_value'], 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Data</th>
                <th>Hora</th>
                <th>Loja</th>
                <th>NF</th>
                <th>Tipo</th>
                <th class="center">E/S</th>
                <th>Ref/Tam</th>
                <th>Barcode</th>
                <th>CPF Cons.</th>
                <th class="right">Qtde</th>
                <th class="right">Preço</th>
                <th class="right">Realizado</th>
                <th class="right">Desc.</th>
                <th class="right">Líquido</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $m)
                <tr>
                    <td>{{ $m->movement_date?->format('d/m/Y') }}</td>
                    <td>{{ $m->movement_time ? substr($m->movement_time, 0, 8) : '-' }}</td>
                    <td>{{ $m->store_code }}</td>
                    <td>{{ $m->invoice_number ?? '-' }}</td>
                    <td>{{ $m->movementType?->description ?? $m->movement_code }}</td>
                    <td class="center">{{ $m->entry_exit === 'E' ? 'E' : 'S' }}</td>
                    <td>{{ $m->ref_size ?? '-' }}</td>
                    <td>{{ $m->barcode ?? '-' }}</td>
                    <td>{{ $m->cpf_consultant ?? '-' }}</td>
                    <td class="right">{{ number_format((float) $m->quantity, 3, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $m->sale_price, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $m->realized_value, 2, ',', '.') }}</td>
                    <td class="right">
                        @if ((float) $m->discount_value > 0)
                            {{ number_format((float) $m->discount_value, 2, ',', '.') }}
                        @else
                            <span class="muted">-</span>
                        @endif
                    </td>
                    <td class="right {{ (float) $m->net_value < 0 ? 'neg' : '' }}">
                        {{ number_format((float) $m->net_value, 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Mercury — Grupo Meia Sola · Relatório de Movimentações Diárias · {{ $generatedAt->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
