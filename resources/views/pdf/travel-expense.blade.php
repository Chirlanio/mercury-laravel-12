<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verba de Viagem #{{ $expense->id }}</title>
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
        .grid-cell { display: table-cell; padding: 4px 8px 4px 0; width: 33.33%; vertical-align: top; }
        .grid-cell-50 { display: table-cell; padding: 4px 8px 4px 0; width: 50%; vertical-align: top; }
        .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .value { font-size: 11px; color: #111; font-weight: bold; }
        .badge {
            display: inline-block; padding: 2px 8px; font-size: 9px;
            border-radius: 10px; font-weight: bold;
        }
        .b-draft { background: #f3f4f6; color: #374151; }
        .b-submitted { background: #fef3c7; color: #92400e; }
        .b-approved { background: #dbeafe; color: #1e40af; }
        .b-rejected { background: #fee2e2; color: #991b1b; }
        .b-finalized { background: #dcfce7; color: #166534; }
        .b-cancelled { background: #fee2e2; color: #991b1b; }
        .b-pending { background: #f3f4f6; color: #6b7280; }
        .b-in_progress { background: #dbeafe; color: #1e40af; }
        .summary {
            background: #eef2ff; border: 1px solid #c7d2fe;
            border-radius: 4px; padding: 10px; margin: 8px 0;
        }
        .summary-row { display: table-row; }
        .summary-cell { display: table-cell; padding: 4px 12px; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9px; }
        table.items th, table.items td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; }
        table.items th { background: #f9fafb; color: #374151; font-size: 8px; text-transform: uppercase; }
        table.items td.num { text-align: right; font-family: Courier, monospace; }
        table.items tfoot td { background: #f3f4f6; font-weight: bold; }
        .timeline-item {
            padding: 4px 0 4px 12px; border-left: 2px solid #c7d2fe;
            margin-bottom: 4px; font-size: 9px;
        }
        .timeline-title { font-weight: bold; color: #4338ca; }
        .timeline-meta { color: #6b7280; font-size: 8px; }
        .timeline-note { color: #4b5563; font-style: italic; margin-top: 2px; }
        .alert-warn { background: #fef3c7; border: 1px solid #fcd34d; padding: 6px 10px; border-radius: 4px; color: #92400e; }
        .alert-danger { background: #fee2e2; border: 1px solid #fca5a5; padding: 6px 10px; border-radius: 4px; color: #991b1b; }
        .footer {
            margin-top: 20px; font-size: 8px; color: #9ca3af;
            text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px;
        }
    </style>
</head>
<body>

@php
    $accounted = (float) $expense->items->sum('value');
    $balance = (float) $expense->value - $accounted;
    $fmt = fn ($v) => 'R$ ' . number_format((float) ($v ?? 0), 2, ',', '.');
@endphp

<div class="header">
    <h1>Comprovante de Verba de Viagem #{{ $expense->id }}</h1>
    <div class="subtitle">
        {{ $expense->origin }} → {{ $expense->destination }} ·
        Gerado em {{ $generatedAt->format('d/m/Y H:i') }}
    </div>
</div>

<div class="section">
    <div class="section-title">Resumo Financeiro</div>
    <div class="summary grid">
        <div class="summary-row">
            <div class="summary-cell">
                <div class="label">Verba aprovada</div>
                <div class="value">{{ $fmt($expense->value) }}</div>
            </div>
            <div class="summary-cell">
                <div class="label">Diária × Dias</div>
                <div class="value">{{ $fmt($expense->daily_rate) }} × {{ $expense->days_count }}</div>
            </div>
            <div class="summary-cell">
                <div class="label">Total prestado</div>
                <div class="value">{{ $fmt($accounted) }}</div>
            </div>
            <div class="summary-cell">
                <div class="label">Saldo</div>
                <div class="value" style="color: {{ $balance < 0 ? '#991b1b' : '#166534' }}">
                    {{ $fmt($balance) }}
                </div>
            </div>
        </div>
    </div>
    <div style="margin-top: 6px;">
        <span class="badge b-{{ $expense->status?->value }}">Solicitação: {{ $expense->status?->label() }}</span>
        &nbsp;
        <span class="badge b-{{ $expense->accountability_status?->value }}">Prestação: {{ $expense->accountability_status?->label() }}</span>
    </div>
</div>

<div class="section">
    <div class="section-title">Dados da Viagem</div>
    <div class="grid">
        <div class="grid-row">
            <div class="grid-cell">
                <div class="label">Beneficiado</div>
                <div class="value">{{ $expense->employee?->name ?? '—' }}</div>
            </div>
            <div class="grid-cell">
                <div class="label">Loja</div>
                <div class="value">{{ $expense->store?->code ?? $expense->store_code ?? '—' }} {{ $expense->store ? '— ' . $expense->store->name : '' }}</div>
            </div>
            <div class="grid-cell">
                <div class="label">Solicitante</div>
                <div class="value">{{ $expense->createdBy?->name ?? '—' }}</div>
            </div>
        </div>
        <div class="grid-row">
            <div class="grid-cell">
                <div class="label">Saída</div>
                <div class="value">{{ $expense->initial_date?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div class="grid-cell">
                <div class="label">Retorno</div>
                <div class="value">{{ $expense->end_date?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div class="grid-cell">
                <div class="label">Dias</div>
                <div class="value">{{ $expense->days_count }}</div>
            </div>
        </div>
        @if ($expense->client_name)
            <div class="grid-row">
                <div class="grid-cell" colspan="3">
                    <div class="label">Cliente / contato</div>
                    <div class="value">{{ $expense->client_name }}</div>
                </div>
            </div>
        @endif
    </div>
    @if ($expense->description)
        <div style="margin-top: 8px;">
            <div class="label">Justificativa</div>
            <div style="font-size: 10px; padding: 6px 8px; background: #f9fafb; border-left: 3px solid #c7d2fe; margin-top: 2px;">
                {{ $expense->description }}
            </div>
        </div>
    @endif
</div>

<div class="section">
    <div class="section-title">Pagamento</div>
    <div class="grid">
        <div class="grid-row">
            @if ($expense->masked_cpf)
                <div class="grid-cell">
                    <div class="label">CPF</div>
                    <div class="value" style="font-family: Courier, monospace;">{{ $expense->masked_cpf }}</div>
                </div>
            @endif
            @if ($expense->bank)
                <div class="grid-cell">
                    <div class="label">Banco</div>
                    <div class="value">{{ $expense->bank->bank_name }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Agência / Conta</div>
                    <div class="value" style="font-family: Courier, monospace;">{{ $expense->bank_branch }} / {{ $expense->bank_account }}</div>
                </div>
            @endif
        </div>
        @if ($expense->pixType)
            <div class="grid-row">
                <div class="grid-cell-50">
                    <div class="label">Tipo de chave PIX</div>
                    <div class="value">{{ $expense->pixType->name }}</div>
                </div>
                <div class="grid-cell-50">
                    <div class="label">Chave PIX</div>
                    <div class="value" style="font-family: Courier, monospace;">{{ $expense->pix_key ?? '—' }}</div>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="section">
    <div class="section-title">Prestação de Contas ({{ $expense->items->count() }} {{ $expense->items->count() === 1 ? 'item' : 'itens' }})</div>
    @if ($expense->items->isEmpty())
        <div style="text-align: center; padding: 8px; color: #9ca3af; font-style: italic;">
            Nenhum item lançado.
        </div>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th>NF</th>
                    <th class="num">Valor</th>
                    <th>Comp.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expense->items as $item)
                    <tr>
                        <td>{{ $item->expense_date?->format('d/m/Y') }}</td>
                        <td>{{ $item->typeExpense?->name ?? '—' }}</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->invoice_number ?? '—' }}</td>
                        <td class="num">{{ $fmt($item->value) }}</td>
                        <td>{{ $item->attachment_path ? 'Sim' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align: right;">Total prestado:</td>
                    <td class="num">{{ $fmt($accounted) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
    @if ($expense->accountability_rejection_reason)
        <div class="alert-danger" style="margin-top: 8px;">
            <strong>Prestação devolvida:</strong> {{ $expense->accountability_rejection_reason }}
        </div>
    @endif
</div>

@if ($expense->rejection_reason || $expense->cancelled_reason || $expense->internal_notes)
    <div class="section">
        <div class="section-title">Observações</div>
        @if ($expense->rejection_reason)
            <div class="alert-danger"><strong>Verba rejeitada:</strong> {{ $expense->rejection_reason }}</div>
        @endif
        @if ($expense->cancelled_reason)
            <div class="alert-danger" style="margin-top: 4px;"><strong>Cancelada:</strong> {{ $expense->cancelled_reason }}</div>
        @endif
        @if ($expense->internal_notes)
            <div class="alert-warn" style="margin-top: 4px;"><strong>Notas internas:</strong> {{ $expense->internal_notes }}</div>
        @endif
    </div>
@endif

<div class="section">
    <div class="section-title">Histórico</div>
    @foreach ($expense->statusHistory as $h)
        <div class="timeline-item">
            <span class="timeline-title">[{{ $h->kind === 'accountability' ? 'Prestação' : 'Solicitação' }}]
                {{ $h->from_status ?? 'Início' }} → {{ $h->to_status }}
            </span>
            <div class="timeline-meta">
                {{ $h->changedBy?->name ?? '—' }} · {{ $h->created_at?->format('d/m/Y H:i') }}
            </div>
            @if ($h->note)
                <div class="timeline-note">"{{ $h->note }}"</div>
            @endif
        </div>
    @endforeach
</div>

<div class="footer">
    Gerado por Mercury · {{ $generatedAt->format('d/m/Y H:i:s') }} · ULID: {{ $expense->ulid }}
</div>

</body>
</html>
