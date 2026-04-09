<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Auditoria de Estoque #{{ $audit->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        h1 { font-size: 18px; color: #1e40af; margin-bottom: 5px; }
        h2 { font-size: 14px; color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 4px; margin-top: 20px; }
        h3 { font-size: 12px; color: #374151; margin-top: 15px; }
        .header { display: flex; justify-content: space-between; border-bottom: 3px solid #1e40af; padding-bottom: 10px; margin-bottom: 15px; }
        .header-info { font-size: 10px; color: #6b7280; }
        .summary-grid { display: table; width: 100%; margin-bottom: 15px; }
        .summary-item { display: table-cell; text-align: center; padding: 8px; border: 1px solid #e5e7eb; }
        .summary-value { font-size: 20px; font-weight: bold; color: #1e40af; }
        .summary-label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .loss { color: #dc2626; }
        .surplus { color: #16a34a; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        th { background-color: #1e40af; color: white; padding: 6px 8px; text-align: left; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fef2f2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .timeline { margin-top: 10px; }
        .timeline-item { padding: 4px 0; border-left: 2px solid #1e40af; padding-left: 10px; margin-left: 5px; }
        .signature-area { display: inline-block; width: 45%; text-align: center; margin-top: 20px; border-top: 1px solid #333; padding-top: 5px; }
        .footer { text-align: center; font-size: 9px; color: #9ca3af; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 5px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <div>
            <h1>Relatório de Auditoria de Estoque #{{ $audit->id }}</h1>
            <div class="header-info">
                Loja: <strong>{{ $audit->store->name ?? '-' }}</strong> |
                Tipo: <strong>{{ \App\Models\StockAudit::AUDIT_TYPES[$audit->audit_type] ?? $audit->audit_type }}</strong> |
                Status: <strong>{{ \App\Models\StockAudit::STATUS_LABELS[$audit->status] ?? $audit->status }}</strong>
            </div>
            @if($audit->vendor)
                <div class="header-info">Empresa Auditora: <strong>{{ $audit->vendor->company_name }}</strong></div>
            @endif
        </div>
        <div class="header-info" style="text-align: right;">
            Gerado em: {{ now()->format('d/m/Y H:i') }}<br>
            Grupo Meia Sola — Mercury
        </div>
    </div>

    {{-- Summary --}}
    <h2>Resumo Executivo</h2>
    <div class="summary-grid">
        <div class="summary-item">
            <div class="summary-value">{{ number_format($summary['accuracy'] ?? 0, 1) }}%</div>
            <div class="summary-label">Acurácia</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">{{ $summary['total_items'] ?? 0 }}</div>
            <div class="summary-label">Itens Contados</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">{{ $summary['total_divergences'] ?? 0 }}</div>
            <div class="summary-label">Divergências</div>
        </div>
        <div class="summary-item">
            <div class="summary-value loss">R$ {{ number_format($summary['financial_loss'] ?? 0, 2, ',', '.') }}</div>
            <div class="summary-label">Perda (Venda)</div>
        </div>
        <div class="summary-item">
            <div class="summary-value surplus">R$ {{ number_format($summary['financial_surplus'] ?? 0, 2, ',', '.') }}</div>
            <div class="summary-label">Sobra (Venda)</div>
        </div>
    </div>

    {{-- Top 10 Losses --}}
    @if($topLosses->count() > 0)
    <h2>Top 10 — Perdas</h2>
    <table>
        <thead>
            <tr>
                <th>Referência</th>
                <th>Descrição</th>
                <th>Tam.</th>
                <th>Sist.</th>
                <th>Contado</th>
                <th>Divergência</th>
                <th>Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topLosses as $item)
            <tr>
                <td>{{ $item->product_reference }}</td>
                <td>{{ \Illuminate\Support\Str::limit($item->product_description, 40) }}</td>
                <td>{{ $item->product_size ?? '-' }}</td>
                <td>{{ number_format((float)$item->system_quantity, 0) }}</td>
                <td>{{ number_format((float)$item->accepted_count, 0) }}</td>
                <td class="loss"><strong>{{ number_format((float)$item->divergence, 0) }}</strong></td>
                <td class="loss">R$ {{ number_format(abs((float)$item->divergence_value), 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Top 10 Surpluses --}}
    @if($topSurpluses->count() > 0)
    <h2>Top 10 — Sobras</h2>
    <table>
        <thead>
            <tr>
                <th>Referência</th>
                <th>Descrição</th>
                <th>Tam.</th>
                <th>Sist.</th>
                <th>Contado</th>
                <th>Divergência</th>
                <th>Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topSurpluses as $item)
            <tr>
                <td>{{ $item->product_reference }}</td>
                <td>{{ \Illuminate\Support\Str::limit($item->product_description, 40) }}</td>
                <td>{{ $item->product_size ?? '-' }}</td>
                <td>{{ number_format((float)$item->system_quantity, 0) }}</td>
                <td>{{ number_format((float)$item->accepted_count, 0) }}</td>
                <td class="surplus"><strong>+{{ number_format((float)$item->divergence, 0) }}</strong></td>
                <td class="surplus">R$ {{ number_format((float)$item->divergence_value, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Timeline --}}
    <h2>Cronologia</h2>
    <div class="timeline">
        @if($timeline['created_at'])
            <div class="timeline-item">Criada em: {{ $timeline['created_at']->format('d/m/Y H:i') }}</div>
        @endif
        @if($timeline['authorized_at'])
            <div class="timeline-item">Autorizada em: {{ $timeline['authorized_at']->format('d/m/Y H:i') }}</div>
        @endif
        @if($timeline['started_at'])
            <div class="timeline-item">Contagem iniciada em: {{ $timeline['started_at']->format('d/m/Y H:i') }}</div>
        @endif
        @if($timeline['finished_at'])
            <div class="timeline-item">Finalizada em: {{ $timeline['finished_at']->format('d/m/Y H:i') }}</div>
        @endif
    </div>

    {{-- Signatures --}}
    @if($audit->signatures->count() > 0)
    <h2>Assinaturas Digitais</h2>
    @foreach($audit->signatures as $signature)
        <div class="signature-area">
            @if($signature->signature_data)
                <img src="{{ $signature->signature_data }}" style="max-width: 200px; max-height: 80px;" alt="Assinatura">
            @endif
            <br>
            <strong>{{ $signature->signerUser->name ?? '-' }}</strong><br>
            <span style="font-size: 9px;">{{ ucfirst($signature->signer_role) }} — {{ $signature->signed_at?->format('d/m/Y H:i') }}</span>
        </div>
    @endforeach
    @endif

    {{-- Team --}}
    @if($audit->teams->count() > 0)
    <h2>Equipe</h2>
    <table>
        <thead>
            <tr><th>Nome</th><th>Função</th><th>Tipo</th></tr>
        </thead>
        <tbody>
            @foreach($audit->teams as $member)
            <tr>
                <td>{{ $member->user?->name ?? $member->external_staff_name ?? '-' }}</td>
                <td>{{ ucfirst($member->role) }}</td>
                <td>{{ $member->is_third_party ? 'Terceirizado' : 'Interno' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Mercury — Grupo Meia Sola | Auditoria de Estoque #{{ $audit->id }} | Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
