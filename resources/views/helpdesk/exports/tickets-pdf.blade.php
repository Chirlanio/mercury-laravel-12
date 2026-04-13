<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Chamados Helpdesk - {{ $generatedAt }}</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        body { margin: 0; padding: 12px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #666; font-size: 8px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #4f46e5; color: #fff; text-align: left;
            padding: 6px 4px; font-size: 8px; text-transform: uppercase;
        }
        tbody td { padding: 5px 4px; border-bottom: 1px solid #eee; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .badge {
            display: inline-block; padding: 1px 5px; border-radius: 3px;
            font-size: 7px; font-weight: bold; color: #fff;
        }
        .b-open { background: #3b82f6; }
        .b-progress { background: #f59e0b; }
        .b-pending { background: #ea580c; }
        .b-resolved { background: #10b981; }
        .b-closed { background: #6b7280; }
        .b-cancelled { background: #ef4444; }
        .b-urgent { background: #dc2626; }
        .b-high { background: #f59e0b; }
        .b-medium { background: #3b82f6; }
        .b-low { background: #6b7280; }
        .overdue { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 18px; color: #888; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <h1>Relatório de Chamados — Helpdesk</h1>
    <div class="meta">
        Gerado em {{ $generatedAt }} · Total: {{ count($tickets) }} chamado(s)
        @if(!empty($filterLabels))
            · Filtros: {{ implode(' | ', $filterLabels) }}
        @endif
        @if(count($tickets) === $maxRows)
            · <strong>Exibindo os primeiros {{ $maxRows }} registros</strong>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Título</th>
                <th>Solicitante</th>
                <th>Depto</th>
                <th>Técnico</th>
                <th>Status</th>
                <th>Prioridade</th>
                <th>SLA</th>
                <th>Criado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $t)
                <tr>
                    <td>#{{ $t->id }}</td>
                    <td>{{ $t->title }}</td>
                    <td>{{ $t->requester?->name ?? '-' }}</td>
                    <td>{{ $t->department?->name ?? '-' }}</td>
                    <td>{{ $t->assignedTechnician?->name ?? '—' }}</td>
                    <td>
                        <span class="badge b-{{ str_replace('_', '-', str_replace(['in_progress'], ['progress'], $t->status)) }}">
                            {{ \App\Models\HdTicket::STATUS_LABELS[$t->status] ?? $t->status }}
                        </span>
                    </td>
                    <td>
                        @php $pri = match($t->priority) { 1=>'low',2=>'medium',3=>'high',4=>'urgent',default=>'medium' }; @endphp
                        <span class="badge b-{{ $pri }}">
                            {{ \App\Models\HdTicket::PRIORITY_LABELS[$t->priority] ?? $t->priority }}
                        </span>
                    </td>
                    <td class="{{ $t->is_overdue ? 'overdue' : '' }}">
                        {{ $t->sla_due_at?->format('d/m H:i') ?? '-' }}
                        @if($t->is_overdue) ⚠ @endif
                    </td>
                    <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Mercury — Grupo Meia Sola · Relatório de Chamados</div>
</body>
</html>
