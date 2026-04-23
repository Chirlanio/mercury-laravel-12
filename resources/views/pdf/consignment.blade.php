<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Consignação #{{ $consignment->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; margin: 20px; }
        h1 { font-size: 18px; color: #4338ca; margin: 0 0 4px; }
        .subtitle { font-size: 10px; color: #6b7280; margin-bottom: 12px; }
        .header {
            border-bottom: 2px solid #4338ca;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-grid { display: table; width: 100%; }
        .header-left { display: table-cell; width: 70%; vertical-align: top; }
        .header-right { display: table-cell; width: 30%; vertical-align: top; text-align: right; }
        .qr-code { width: 120px; }
        .qr-hint { font-size: 8px; color: #6b7280; margin-top: 2px; }

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

        .badge {
            display: inline-block; padding: 2px 8px; font-size: 9px;
            border-radius: 10px; font-weight: bold;
        }
        .b-draft { background: #f3f4f6; color: #374151; }
        .b-pending { background: #dbeafe; color: #1e40af; }
        .b-partially_returned { background: #fef3c7; color: #92400e; }
        .b-overdue { background: #fee2e2; color: #991b1b; }
        .b-completed { background: #dcfce7; color: #166534; }
        .b-cancelled { background: #f3f4f6; color: #6b7280; }

        .type-cliente { background: #dbeafe; color: #1e40af; }
        .type-influencer { background: #f3e8ff; color: #6b21a8; }
        .type-ecommerce { background: #ccfbf1; color: #115e59; }

        table.items {
            width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 9px;
        }
        table.items th {
            background: #f3f4f6; padding: 6px 8px; text-align: left;
            border-bottom: 1px solid #d1d5db; font-weight: bold; color: #374151;
        }
        table.items td {
            padding: 5px 8px; border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        table.items .num { text-align: right; }

        .totals {
            margin-top: 10px; background: #eef2ff; border: 1px solid #c7d2fe;
            border-radius: 4px; padding: 10px;
        }
        .totals-grid { display: table; width: 100%; }
        .totals-cell {
            display: table-cell; width: 25%; padding: 4px;
            text-align: center; vertical-align: top;
        }
        .totals-cell .lbl { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .totals-cell .val { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .totals-cell.total .val { color: #4338ca; }

        .signature-area {
            margin-top: 30px; display: table; width: 100%;
        }
        .sig-cell {
            display: table-cell; width: 50%; padding: 0 20px;
            text-align: center; vertical-align: bottom;
        }
        .sig-line {
            border-top: 1px solid #111; margin-top: 40px;
            padding-top: 4px; font-size: 9px; color: #374151;
        }

        .footer {
            margin-top: 25px; padding-top: 10px;
            border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af;
            text-align: center;
        }

        .instructions {
            background: #fffbeb; border: 1px solid #fcd34d;
            border-radius: 4px; padding: 10px; margin-top: 10px;
            font-size: 9px; color: #78350f;
        }
        .instructions strong { color: #92400e; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-grid">
            <div class="header-left">
                <h1>Comprovante de Consignação #{{ $consignment->id }}</h1>
                <div class="subtitle">
                    Emitido em {{ $generatedAt->format('d/m/Y H:i') }}
                    &middot;
                    <span class="badge type-{{ $consignment->type->value }}">
                        {{ $consignment->type->label() }}
                    </span>
                    &middot;
                    <span class="badge b-{{ $consignment->status->value }}">
                        {{ $consignment->status->label() }}
                    </span>
                </div>
            </div>
            <div class="header-right">
                @if ($qrDataUri)
                    <img src="{{ $qrDataUri }}" class="qr-code" alt="QR Code">
                    <div class="qr-hint">Escaneie para abrir no app</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Dados da consignação -->
    <div class="section">
        <div class="section-title">Dados gerais</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Loja de origem</div>
                    <div class="value">
                        {{ $consignment->store?->code }}
                        @if ($consignment->store?->name)
                            — {{ $consignment->store->name }}
                        @endif
                    </div>
                </div>
                <div class="grid-cell">
                    <div class="label">Consultor(a) responsável</div>
                    <div class="value">{{ $consignment->employee?->name ?? '—' }}</div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Nota fiscal de saída</div>
                    <div class="value">{{ $consignment->outbound_invoice_number }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Data da NF</div>
                    <div class="value">{{ $consignment->outbound_invoice_date?->format('d/m/Y') ?? '—' }}</div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Prazo de retorno</div>
                    <div class="value">
                        {{ $consignment->expected_return_date?->format('d/m/Y') ?? '—' }}
                        ({{ $consignment->return_period_days }} dias)
                    </div>
                </div>
                <div class="grid-cell">
                    <div class="label">Criada por</div>
                    <div class="value">{{ $consignment->createdBy?->name ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Destinatário -->
    <div class="section">
        <div class="section-title">Destinatário</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Nome</div>
                    <div class="value">{{ $consignment->recipient_name }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Documento (CPF/CNPJ)</div>
                    <div class="value">{{ $consignment->recipient_document ?? '—' }}</div>
                </div>
            </div>
            @if ($consignment->recipient_phone || $consignment->recipient_email)
                <div class="grid-row">
                    @if ($consignment->recipient_phone)
                        <div class="grid-cell">
                            <div class="label">Telefone</div>
                            <div class="value">{{ $consignment->recipient_phone }}</div>
                        </div>
                    @endif
                    @if ($consignment->recipient_email)
                        <div class="grid-cell">
                            <div class="label">E-mail</div>
                            <div class="value">{{ $consignment->recipient_email }}</div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Itens -->
    <div class="section">
        <div class="section-title">
            Itens consignados ({{ count($consignment->items) }})
        </div>
        <table class="items">
            <thead>
                <tr>
                    <th>Referência</th>
                    <th>Descrição</th>
                    <th>Tam.</th>
                    <th class="num">Qtd</th>
                    <th class="num">Valor Unit.</th>
                    <th class="num">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($consignment->items as $item)
                    <tr>
                        <td><strong>{{ $item->reference }}</strong></td>
                        <td>{{ $item->description ?? '—' }}</td>
                        <td>{{ $item->size_label ?? $item->size_cigam_code ?? '—' }}</td>
                        <td class="num">{{ (int) $item->quantity }}</td>
                        <td class="num">R$ {{ number_format((float) $item->unit_value, 2, ',', '.') }}</td>
                        <td class="num">R$ {{ number_format((float) $item->total_value, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #9ca3af;">
                            Sem itens
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Totais -->
        <div class="totals">
            <div class="totals-grid">
                <div class="totals-cell total">
                    <div class="lbl">Total enviado</div>
                    <div class="val">R$ {{ number_format((float) $consignment->outbound_total_value, 2, ',', '.') }}</div>
                    <div class="lbl">{{ (int) $consignment->outbound_items_count }} peça(s)</div>
                </div>
                <div class="totals-cell">
                    <div class="lbl">Devolvido</div>
                    <div class="val" style="color: #15803d;">R$ {{ number_format((float) $consignment->returned_total_value, 2, ',', '.') }}</div>
                    <div class="lbl">{{ (int) $consignment->returned_items_count }} peça(s)</div>
                </div>
                <div class="totals-cell">
                    <div class="lbl">Vendido</div>
                    <div class="val" style="color: #1e40af;">R$ {{ number_format((float) $consignment->sold_total_value, 2, ',', '.') }}</div>
                    <div class="lbl">{{ (int) $consignment->sold_items_count }} peça(s)</div>
                </div>
                <div class="totals-cell">
                    <div class="lbl">Perdido</div>
                    <div class="val" style="color: #b91c1c;">R$ {{ number_format((float) $consignment->lost_total_value, 2, ',', '.') }}</div>
                    <div class="lbl">{{ (int) $consignment->lost_items_count }} peça(s)</div>
                </div>
            </div>
        </div>
    </div>

    @if ($consignment->notes)
        <div class="section">
            <div class="section-title">Observações</div>
            <div style="font-size: 10px; color: #374151; white-space: pre-wrap;">{{ $consignment->notes }}</div>
        </div>
    @endif

    <!-- Instruções de devolução -->
    @if (! in_array($consignment->status->value, ['completed', 'cancelled']))
        <div class="instructions">
            <strong>Instruções de retorno:</strong> os produtos acima devem retornar à loja
            de origem até <strong>{{ $consignment->expected_return_date?->format('d/m/Y') ?? 'a data combinada' }}</strong>.
            No ato da devolução, apresente este comprovante. Itens vendidos devem ser pagos
            diretamente à loja para quitar a consignação.
        </div>
    @endif

    <!-- Assinaturas -->
    <div class="signature-area">
        <div class="sig-cell">
            <div class="sig-line">
                Assinatura do destinatário<br>
                {{ $consignment->recipient_name }}
                @if ($consignment->recipient_document)
                    <br>CPF/CNPJ: {{ $consignment->recipient_document }}
                @endif
            </div>
        </div>
        <div class="sig-cell">
            <div class="sig-line">
                Consultor(a) responsável<br>
                {{ $consignment->employee?->name ?? $consignment->createdBy?->name ?? '' }}
            </div>
        </div>
    </div>

    <div class="footer">
        Consignação #{{ $consignment->id }} · UUID {{ $consignment->uuid }}<br>
        Gerado em {{ $generatedAt->format('d/m/Y H:i:s') }} · Mercury Laravel
    </div>
</body>
</html>
