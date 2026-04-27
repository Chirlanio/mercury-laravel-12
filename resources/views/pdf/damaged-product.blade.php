<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Laudo de Produto Avariado #{{ $product->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; margin: 20px; }
        h1 { font-size: 18px; color: #b91c1c; margin: 0 0 4px; }
        .subtitle { font-size: 10px; color: #6b7280; margin-bottom: 12px; }
        .header { border-bottom: 2px solid #b91c1c; padding-bottom: 10px; margin-bottom: 15px; }
        .section { margin-bottom: 14px; page-break-inside: avoid; }
        .section-title {
            font-size: 10px; font-weight: bold; color: #b91c1c;
            text-transform: uppercase; border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px; margin-bottom: 6px;
        }
        .grid { display: table; width: 100%; }
        .grid-row { display: table-row; }
        .grid-cell {
            display: table-cell; padding: 4px 8px 4px 0;
            width: 50%; vertical-align: top;
        }
        .grid-cell.third { width: 33.33%; }
        .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .value { font-size: 11px; color: #111; font-weight: bold; }
        .badge {
            display: inline-block; padding: 2px 8px; font-size: 9px;
            border-radius: 10px; font-weight: bold;
        }
        .b-open { background: #f3f4f6; color: #374151; }
        .b-matched { background: #dbeafe; color: #1e40af; }
        .b-transfer_requested { background: #fef3c7; color: #92400e; }
        .b-resolved { background: #dcfce7; color: #166534; }
        .b-cancelled { background: #fee2e2; color: #991b1b; }
        .b-mismatched { background: #fef3c7; color: #92400e; }
        .b-damaged { background: #fee2e2; color: #991b1b; }
        .photos { display: table; width: 100%; }
        .photo-cell {
            display: table-cell; width: 25%; padding: 4px;
            vertical-align: top; text-align: center;
        }
        .photo-cell img {
            max-width: 100%; max-height: 120px; border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        .timeline { margin-top: 6px; }
        .timeline-item {
            padding: 4px 0 4px 12px; border-left: 2px solid #c7d2fe;
            margin-bottom: 4px; font-size: 10px;
        }
        .timeline-item.cascade { border-left-color: #a855f7; }
        .matches-table {
            width: 100%; border-collapse: collapse; font-size: 9px;
            margin-top: 4px;
        }
        .matches-table th, .matches-table td {
            border: 1px solid #d1d5db; padding: 4px 6px; text-align: left;
        }
        .matches-table th { background: #fef2f2; font-weight: bold; color: #b91c1c; }
        .footer {
            position: fixed; bottom: 10px; left: 20px; right: 20px;
            font-size: 8px; color: #9ca3af; text-align: center;
            border-top: 1px solid #e5e7eb; padding-top: 4px;
        }
        .description-box {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px;
            padding: 8px; font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laudo de Produto Avariado #{{ $product->id }}</h1>
        <div class="subtitle">
            ULID: {{ $product->ulid }} ·
            Loja {{ $product->store?->code }} — {{ $product->store?->name }} ·
            Status: <span class="badge b-{{ $product->status?->value }}">{{ $product->status?->label() }}</span>
        </div>
    </div>

    {{-- Identificação --}}
    <div class="section">
        <div class="section-title">Identificação</div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Referência</div>
                    <div class="value">{{ $product->product_reference }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Marca</div>
                    <div class="value">{{ $product->brand_name ?: ($product->brand_cigam_code ?: '—') }}</div>
                </div>
            </div>
            <div class="grid-row">
                <div class="grid-cell">
                    <div class="label">Descrição</div>
                    <div class="value">{{ $product->product_name ?: '—' }}</div>
                </div>
                <div class="grid-cell">
                    <div class="label">Cor</div>
                    <div class="value">{{ $product->product_color ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tipo de problema --}}
    <div class="section">
        <div class="section-title">Tipo do problema</div>
        @if($product->is_mismatched)
            <span class="badge b-mismatched">Par trocado</span>
        @endif
        @if($product->is_damaged)
            <span class="badge b-damaged">Avariado</span>
        @endif
    </div>

    {{-- Mismatched details --}}
    @if($product->is_mismatched)
        <div class="section">
            <div class="section-title">Detalhes do par trocado</div>
            <div class="grid">
                <div class="grid-row">
                    <div class="grid-cell">
                        <div class="label">Tamanho do pé esquerdo</div>
                        <div class="value">{{ $product->mismatched_left_size }}</div>
                    </div>
                    <div class="grid-cell">
                        <div class="label">Tamanho do pé direito</div>
                        <div class="value">{{ $product->mismatched_right_size }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Damaged details --}}
    @if($product->is_damaged)
        <div class="section">
            <div class="section-title">Detalhes da avaria</div>
            <div class="grid">
                <div class="grid-row">
                    <div class="grid-cell third">
                        <div class="label">Tipo de dano</div>
                        <div class="value">{{ $product->damageType?->name ?: '—' }}</div>
                    </div>
                    <div class="grid-cell third">
                        <div class="label">Pé(s) avariado(s)</div>
                        <div class="value">{{ $product->damaged_foot?->label() }}</div>
                    </div>
                    <div class="grid-cell third">
                        <div class="label">Tamanho avariado</div>
                        <div class="value">{{ $product->damaged_size ?: '—' }}</div>
                    </div>
                </div>
                @if($product->is_repairable)
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Reparável</div>
                            <div class="value">Sim</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Custo estimado de reparo</div>
                            <div class="value">
                                @if($product->estimated_repair_cost)
                                    R$ {{ number_format((float) $product->estimated_repair_cost, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            @if($product->damage_description)
                <div class="description-box" style="margin-top: 8px;">
                    {{ $product->damage_description }}
                </div>
            @endif
        </div>
    @endif

    {{-- Fotos (até 8 — paginação automática do DomPDF) --}}
    @if($product->photos->count() > 0)
        <div class="section">
            <div class="section-title">Fotos do dano ({{ $product->photos->count() }})</div>
            <div class="photos">
                @foreach($product->photos->take(8)->chunk(4) as $chunk)
                    <div class="grid-row" style="display:table-row;">
                        @foreach($chunk as $photo)
                            <div class="photo-cell">
                                @php
                                    $absolutePath = storage_path('app/public/' . $photo->file_path);
                                @endphp
                                @if(file_exists($absolutePath))
                                    <img src="{{ $absolutePath }}" alt="{{ $photo->filename }}">
                                @endif
                                @if($photo->caption)
                                    <div style="font-size: 8px; color: #6b7280; margin-top: 2px;">{{ $photo->caption }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Notas --}}
    @if($product->notes)
        <div class="section">
            <div class="section-title">Observações</div>
            <div class="description-box">{{ $product->notes }}</div>
        </div>
    @endif

    {{-- Cancelamento --}}
    @if($product->status?->value === 'cancelled')
        <div class="section">
            <div class="section-title">Cancelamento</div>
            <div class="grid">
                <div class="grid-row">
                    <div class="grid-cell">
                        <div class="label">Cancelado em</div>
                        <div class="value">{{ $product->cancelled_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="grid-cell">
                        <div class="label">Cancelado por</div>
                        <div class="value">{{ $product->cancelledBy?->name ?: '—' }}</div>
                    </div>
                </div>
            </div>
            @if($product->cancel_reason)
                <div class="description-box" style="margin-top: 8px;">{{ $product->cancel_reason }}</div>
            @endif
        </div>
    @endif

    {{-- Matches --}}
    @if($matches->count() > 0)
        <div class="section">
            <div class="section-title">Matches identificados ({{ $matches->count() }})</div>
            <table class="matches-table">
                <thead>
                    <tr>
                        <th>#</th><th>Tipo</th><th>Status</th><th>Score</th>
                        <th>Loja A</th><th>Loja B</th><th>Origem → Destino</th>
                        <th>NF transferência</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($matches as $m)
                        <tr>
                            <td>{{ $m->id }}</td>
                            <td>{{ $m->match_type?->label() }}</td>
                            <td>{{ $m->status?->label() }}</td>
                            <td>{{ number_format((float) $m->match_score, 1, ',', '.') }}</td>
                            <td>{{ $m->productA?->store?->code }}</td>
                            <td>{{ $m->productB?->store?->code }}</td>
                            <td>{{ $m->suggestedOriginStore?->code }} → {{ $m->suggestedDestinationStore?->code }}</td>
                            <td>{{ $m->transfer?->invoice_number ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Histórico --}}
    @if($product->statusHistory->count() > 0)
        <div class="section">
            <div class="section-title">Histórico de status</div>
            <div class="timeline">
                @foreach($product->statusHistory as $h)
                    <div class="timeline-item {{ $h->triggered_by_match_id ? 'cascade' : '' }}">
                        <strong>{{ $h->from_status ?? '—' }} → {{ $h->to_status }}</strong>
                        · {{ $h->actor?->name ?? 'Sistema' }}
                        · {{ $h->created_at?->format('d/m/Y H:i') }}
                        @if($h->triggered_by_match_id)
                            (cascata via match #{{ $h->triggered_by_match_id }})
                        @endif
                        @if($h->note)
                            <div style="color: #6b7280; font-size: 9px; margin-top: 1px;">{{ $h->note }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="footer">
        Gerado em {{ $generatedAt->format('d/m/Y H:i:s') }} · Mercury — Módulo Produtos Avariados
    </div>
</body>
</html>
