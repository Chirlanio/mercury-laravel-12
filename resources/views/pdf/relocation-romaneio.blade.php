<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Romaneio de Separação — Remanejo #{{ $relocation->id }}</title>
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
        .route {
            background: #eef2ff; border: 1px solid #c7d2fe;
            border-radius: 4px; padding: 12px; margin: 8px 0;
            text-align: center;
        }
        .route .from-to {
            font-size: 16px; color: #4338ca; font-weight: bold;
        }
        .route .arrow { font-size: 18px; color: #6366f1; margin: 0 8px; }
        .route .meta { font-size: 9px; color: #6b7280; margin-top: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th {
            background: #4338ca; color: #fff; padding: 5px;
            font-size: 8px; text-align: left; text-transform: uppercase;
        }
        table.items td { padding: 5px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        table.items tr:nth-child(even) { background: #f9fafb; }
        .right { text-align: right; }
        .center { text-align: center; }
        .badge {
            display: inline-block; padding: 2px 8px; font-size: 9px;
            border-radius: 10px; font-weight: bold;
        }
        .b-priority-low { background: #f3f4f6; color: #374151; }
        .b-priority-normal { background: #dbeafe; color: #1e40af; }
        .b-priority-high { background: #fef3c7; color: #92400e; }
        .b-priority-urgent { background: #fee2e2; color: #991b1b; }
        .checkbox {
            display: inline-block; width: 12px; height: 12px;
            border: 1.5px solid #4338ca; border-radius: 2px; vertical-align: middle;
        }
        .signatures { margin-top: 30px; }
        .signature-row { display: table; width: 100%; margin-top: 30px; }
        .signature-cell {
            display: table-cell; width: 50%; padding: 0 10px;
            vertical-align: bottom; text-align: center;
        }
        .signature-line {
            border-top: 1px solid #4338ca;
            padding-top: 4px;
            font-size: 9px; color: #4b5563;
        }
        .footer {
            margin-top: 20px; font-size: 8px; color: #9ca3af;
            text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Romaneio de Separação</h1>
        <div class="subtitle">
            Remanejo #{{ $relocation->id }} · Gerado em {{ $generatedAt->format('d/m/Y H:i') }}
            · Grupo Meia Sola — Mercury
        </div>
    </div>

    <div class="route">
        <span class="from-to">{{ $relocation->originStore?->code ?? '—' }}</span>
        <span class="arrow">&rarr;</span>
        <span class="from-to">{{ $relocation->destinationStore?->code ?? '—' }}</span>
        <div class="meta">
            {{ $relocation->originStore?->name }} &nbsp;&rarr;&nbsp;
            {{ $relocation->destinationStore?->name }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Dados do remanejo</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Tipo</div>
                    <div class="value">{{ $relocation->type?->name ?? '—' }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Prioridade</div>
                    <div class="value">
                        <span class="badge b-priority-{{ $relocation->priority?->value }}">
                            {{ $relocation->priority?->label() }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Status atual</div>
                    <div class="value">{{ $relocation->status?->label() }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Prazo</div>
                    <div class="value">
                        @if ($relocation->deadline_days && $relocation->approved_at)
                            {{ $relocation->approved_at->copy()->addDays($relocation->deadline_days)->format('d/m/Y') }}
                            ({{ $relocation->deadline_days }} dias)
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
            @if ($relocation->title)
            <div class="grid-row">
                <div class="grid-cell" style="width: 100%;" colspan="2">
                    <div class="label">Título</div>
                    <div class="value">{{ $relocation->title }}</div>
                </div>
            </div>
            @endif
            @if ($relocation->observations)
            <div class="grid-row">
                <div class="grid-cell" style="width: 100%;" colspan="2">
                    <div class="label">Observações</div>
                    <div class="value" style="font-weight: normal; font-size: 10px;">
                        {{ $relocation->observations }}
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    @if ($relocation->invoice_number)
    <div class="section">
        <div class="section-title">Nota Fiscal de Transferência</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Número da NF</div>
                    <div class="value">{{ $relocation->invoice_number }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Data</div>
                    <div class="value">
                        {{ optional($relocation->invoice_date)->format('d/m/Y') ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Itens para separar ({{ $relocation->items->count() }})</div>
        <table class="items">
            <thead>
                <tr>
                    <th class="center" style="width: 5%;">✓</th>
                    <th style="width: 18%;">Referência</th>
                    <th style="width: 30%;">Produto / Cor</th>
                    <th style="width: 8%;">Tam.</th>
                    <th style="width: 17%;">Cód. barras</th>
                    <th class="right" style="width: 8%;">Solic.</th>
                    <th class="right" style="width: 7%;">Sep.</th>
                    <th style="width: 7%;">Obs.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($relocation->items as $item)
                <tr>
                    <td class="center"><span class="checkbox"></span></td>
                    <td style="font-family: monospace;">{{ $item->product_reference }}</td>
                    <td>
                        {{ $item->product_name ?: '—' }}
                        @if ($item->product_color)
                            <br><span style="color: #6b7280; font-size: 8px;">{{ $item->product_color }}</span>
                        @endif
                    </td>
                    <td>{{ $item->size ?? '—' }}</td>
                    <td style="font-family: monospace; font-size: 8px;">{{ $item->barcode ?? '—' }}</td>
                    <td class="right" style="font-weight: bold;">{{ $item->qty_requested }}</td>
                    <td class="right">_____</td>
                    <td>&nbsp;</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Totais</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Total de itens (linhas)</div>
                    <div class="value">{{ $relocation->items->count() }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Total de unidades solicitadas</div>
                    <div class="value">{{ $relocation->items->sum('qty_requested') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="signatures">
        <div class="signature-row">
            <div class="signature-cell">
                <div class="signature-line">
                    Separado por (loja {{ $relocation->originStore?->code }})
                </div>
            </div>
            <div class="signature-cell">
                <div class="signature-line">
                    Conferido por
                </div>
            </div>
        </div>
        <div class="signature-row">
            <div class="signature-cell">
                <div class="signature-line">
                    Recebido por (loja {{ $relocation->destinationStore?->code }})
                </div>
            </div>
            <div class="signature-cell">
                <div class="signature-line">
                    Data / Hora
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        Mercury Laravel · Grupo Meia Sola · Documento gerado automaticamente
    </div>
</body>
</html>
