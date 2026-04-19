<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Nota Fiscal {{ $header['invoice_number'] }} · Loja {{ $header['store_code'] }}</title>
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
        .totals-grid { display: table; width: 100%; }
        .totals-grid .grid-cell { width: 25%; text-align: center; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th {
            background: #4338ca; color: #fff; padding: 5px;
            font-size: 8px; text-align: left; text-transform: uppercase;
        }
        table.items td { padding: 4px 5px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        table.items tr:nth-child(even) { background: #f9fafb; }
        table.items tfoot td {
            background: #eef2ff; font-weight: bold;
            border-top: 2px solid #4338ca; border-bottom: none;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #9ca3af; }
        .neg { color: #b91c1c; }
        .badge {
            display: inline-block; padding: 2px 8px; font-size: 9px;
            border-radius: 10px; font-weight: bold;
            background: #e0e7ff; color: #4338ca;
        }
        .footer {
            margin-top: 20px; font-size: 8px; color: #9ca3af;
            text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Nota Fiscal {{ $header['invoice_number'] }}</h1>
        <div class="subtitle">
            Loja {{ $header['store_code'] }}{{ $header['store_name'] ? ' · '.$header['store_name'] : '' }}
            · Gerado em {{ $generatedAt->format('d/m/Y H:i') }} · Grupo Meia Sola — Mercury
        </div>
    </div>

    <div class="section">
        <div class="section-title">Dados da Nota</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Número da NF</div>
                    <div class="value">{{ $header['invoice_number'] }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Loja</div>
                    <div class="value">
                        {{ $header['store_code'] }}@if ($header['store_name'])
                            · {{ $header['store_name'] }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Data / Hora</div>
                    <div class="value">
                        {{ $header['movement_date'] ? \Carbon\Carbon::parse($header['movement_date'])->format('d/m/Y') : '-' }}
                        @if ($header['movement_time'])
                            · {{ $header['movement_time'] }}
                        @endif
                    </div>
                </div>
                <div class="grid-cell">
                    <div class="label">Sincronizado em</div>
                    <div class="value">
                        {{ $header['synced_at'] ? \Carbon\Carbon::parse($header['synced_at'])->format('d/m/Y H:i') : '-' }}
                    </div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">CPF Cliente</div>
                    <div class="value">{{ $header['cpf_customer'] ?: '-' }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">CPF Consultor</div>
                    <div class="value">{{ $header['cpf_consultant'] ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Totais</div>
        <div class="highlight">
            <div class="totals-grid">
                <div class="grid-row">
                    <div class="grid-cell">
                        <div class="label">Itens</div>
                        <div class="value">{{ number_format($totals['items'], 0, ',', '.') }}</div>
                    </div>
                    <div class="grid-cell">
                        <div class="label">Quantidade</div>
                        <div class="value">{{ number_format($totals['quantity'], 3, ',', '.') }}</div>
                    </div>
                    <div class="grid-cell">
                        <div class="label">Valor Realizado</div>
                        <div class="value-money">R$ {{ number_format($totals['realized_value'], 2, ',', '.') }}</div>
                    </div>
                    <div class="grid-cell">
                        <div class="label">Valor Líquido</div>
                        <div class="value-money {{ $totals['net_value'] < 0 ? 'neg' : '' }}">
                            R$ {{ number_format($totals['net_value'], 2, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Itens ({{ $totals['items'] }})</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Tipo</th>
                    <th class="center">E/S</th>
                    <th>Ref/Tam</th>
                    <th>Barcode</th>
                    <th class="right">Qtde</th>
                    <th class="right">Preço</th>
                    <th class="right">Desc.</th>
                    <th class="right">Líquido</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['movement_date'] }}</td>
                        <td>{{ $item['movement_time'] ?? '-' }}</td>
                        <td>{{ $item['movement_type'] }}</td>
                        <td class="center">{{ $item['entry_exit'] === 'E' ? 'E' : 'S' }}</td>
                        <td>{{ $item['ref_size'] ?? '-' }}</td>
                        <td>{{ $item['barcode'] ?? '-' }}</td>
                        <td class="right">{{ number_format($item['quantity'], 3, ',', '.') }}</td>
                        <td class="right">{{ number_format($item['sale_price'], 2, ',', '.') }}</td>
                        <td class="right">
                            @if ($item['discount_value'] > 0)
                                {{ number_format($item['discount_value'], 2, ',', '.') }}
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td class="right {{ $item['net_value'] < 0 ? 'neg' : '' }}">
                            {{ number_format($item['net_value'], 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="right">TOTAIS</td>
                    <td class="right">{{ number_format($totals['quantity'], 3, ',', '.') }}</td>
                    <td></td>
                    <td class="right">
                        @if ($totals['discount_value'] > 0)
                            {{ number_format($totals['discount_value'], 2, ',', '.') }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="right {{ $totals['net_value'] < 0 ? 'neg' : '' }}">
                        {{ number_format($totals['net_value'], 2, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        NF {{ $header['invoice_number'] }} · Loja {{ $header['store_code'] }} ·
        {{ $totals['items'] }} {{ $totals['items'] === 1 ? 'item' : 'itens' }} ·
        Gerado em {{ $generatedAt->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
