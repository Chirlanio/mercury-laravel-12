<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Cupom #{{ $coupon->id }}</title>
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
        .code {
            display: inline-block; padding: 6px 14px; font-size: 14px;
            background: #dcfce7; color: #166534; border: 1px solid #86efac;
            border-radius: 6px; font-family: Courier, monospace; font-weight: bold;
            letter-spacing: 1px;
        }
        .code-pending {
            background: #fef3c7; color: #92400e; border-color: #fcd34d;
        }
        .highlight {
            background: #eef2ff; border: 1px solid #c7d2fe;
            border-radius: 4px; padding: 10px; margin: 8px 0;
        }
        .badge {
            display: inline-block; padding: 2px 8px; font-size: 9px;
            border-radius: 10px; font-weight: bold;
        }
        .b-draft { background: #f3f4f6; color: #374151; }
        .b-requested { background: #fef3c7; color: #92400e; }
        .b-issued { background: #dbeafe; color: #1e40af; }
        .b-active { background: #dcfce7; color: #166534; }
        .b-expired { background: #f3f4f6; color: #6b7280; }
        .b-cancelled { background: #fee2e2; color: #991b1b; }
        .timeline { margin-top: 6px; }
        .timeline-item {
            padding: 4px 0 4px 12px; border-left: 2px solid #c7d2fe;
            margin-bottom: 4px; font-size: 9px;
        }
        .timeline-title { font-weight: bold; color: #4338ca; }
        .timeline-meta { color: #6b7280; font-size: 8px; }
        .timeline-note { color: #4b5563; font-style: italic; margin-top: 2px; }
        .footer {
            margin-top: 20px; font-size: 8px; color: #9ca3af;
            text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Comprovante de Cupom #{{ $coupon->id }}</h1>
    <div class="subtitle">
        {{ $coupon->type?->label() }} · Gerado em {{ $generatedAt->format('d/m/Y H:i') }}
    </div>
</div>

<div class="section">
    <div class="section-title">Código</div>
    <div style="text-align: center; padding: 8px 0;">
        @if ($coupon->coupon_site)
            <div class="code">{{ $coupon->coupon_site }}</div>
            <div class="subtitle" style="margin-top: 6px;">Código emitido na plataforma</div>
        @elseif ($coupon->suggested_coupon)
            <div class="code code-pending">{{ $coupon->suggested_coupon }}</div>
            <div class="subtitle" style="margin-top: 6px;">Sugerido · aguardando emissão pelo e-commerce</div>
        @else
            <div class="subtitle">— ainda sem código —</div>
        @endif
    </div>
    <div class="grid">
        <div class="grid-row">
            <div class="grid-cell">
                <div class="label">Status</div>
                <div class="badge b-{{ $coupon->status?->value }}">{{ $coupon->status?->label() }}</div>
            </div>
            <div class="grid-cell">
                <div class="label">Tipo</div>
                <div class="value">{{ $coupon->type?->label() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-title">Beneficiário</div>
    <div class="grid">
        <div class="grid-row">
            <div class="grid-cell">
                <div class="label">Nome</div>
                <div class="value">{{ $coupon->beneficiary_name ?: '—' }}</div>
            </div>
            <div class="grid-cell">
                <div class="label">CPF (mascarado)</div>
                <div class="value">{{ $coupon->masked_cpf ?: '—' }}</div>
            </div>
        </div>
        @if ($coupon->store_code)
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Loja</div>
                    <div class="value">{{ $coupon->store_code }} — {{ $coupon->store?->name }}</div>
                </div>
                <div class="grid-cell"></div>
            </div>
        @endif
        @if ($coupon->city || $coupon->socialMedia)
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Cidade</div>
                    <div class="value">{{ $coupon->city ?: '—' }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Rede social</div>
                    <div class="value">{{ $coupon->socialMedia?->name ?: '—' }}</div>
                </div>
            </div>
        @endif
        @if ($coupon->social_media_link)
            <div class="grid-row">
                <div class="grid-cell" style="width: 100%;">
                    <div class="label">Link</div>
                    <div class="value">{{ $coupon->social_media_link }}</div>
                </div>
            </div>
        @endif
    </div>
</div>

@if ($coupon->campaign_name || $coupon->valid_from || $coupon->valid_until || $coupon->max_uses)
    <div class="section">
        <div class="section-title">Campanha e validade</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Campanha</div>
                    <div class="value">{{ $coupon->campaign_name ?: '—' }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Validade</div>
                    <div class="value">
                        {{ $coupon->valid_from?->format('d/m/Y') ?: '—' }}
                        →
                        {{ $coupon->valid_until?->format('d/m/Y') ?: '—' }}
                    </div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Usos</div>
                    <div class="value">
                        {{ (int) $coupon->usage_count }}
                        @if ($coupon->max_uses)
                            / {{ $coupon->max_uses }}
                        @endif
                    </div>
                </div>
                <div class="grid-cell"></div>
            </div>
        </div>
    </div>
@endif

@if ($coupon->notes)
    <div class="section">
        <div class="section-title">Observações</div>
        <div class="highlight">{{ $coupon->notes }}</div>
    </div>
@endif

@if ($coupon->cancelled_reason)
    <div class="section">
        <div class="section-title">Cancelamento</div>
        <div class="highlight" style="background:#fee2e2;border-color:#fecaca;">
            {{ $coupon->cancelled_reason }}
            @if ($coupon->cancelled_at)
                <div class="timeline-meta" style="margin-top: 4px;">em {{ $coupon->cancelled_at->format('d/m/Y H:i') }}</div>
            @endif
        </div>
    </div>
@endif

@if ($coupon->statusHistory && $coupon->statusHistory->count())
    <div class="section">
        <div class="section-title">Histórico</div>
        <div class="timeline">
            @foreach ($coupon->statusHistory->sortBy('created_at') as $h)
                <div class="timeline-item">
                    <div class="timeline-title">{{ $h->to_status?->label() }}</div>
                    <div class="timeline-meta">
                        {{ $h->created_at?->format('d/m/Y H:i') }}
                        @if ($h->changedBy)
                            · por {{ $h->changedBy->name }}
                        @endif
                    </div>
                    @if ($h->note)
                        <div class="timeline-note">"{{ $h->note }}"</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="footer">
    Cupom #{{ $coupon->id }} · Documento gerado automaticamente pelo Mercury · {{ $generatedAt->format('d/m/Y H:i:s') }}
</div>

</body>
</html>
