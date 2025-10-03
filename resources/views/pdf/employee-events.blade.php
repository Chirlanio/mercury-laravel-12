<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Eventos - {{ $employee['name'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 3px solid #4F46E5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 20px;
            color: #4F46E5;
        }
        .header p {
            margin: 3px 0;
            color: #666;
        }
        .employee-info {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .employee-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .employee-info td {
            padding: 4px 8px;
        }
        .employee-info td:first-child {
            font-weight: bold;
            width: 30%;
            color: #6B7280;
        }
        .filters {
            background-color: #EEF2FF;
            border: 1px solid #C7D2FE;
            border-radius: 5px;
            padding: 10px 12px;
            margin-bottom: 20px;
        }
        .filters h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #4F46E5;
        }
        .filters p {
            margin: 3px 0;
            font-size: 10px;
        }
        .filters strong {
            color: #4338CA;
        }
        .events-section {
            margin-top: 20px;
        }
        .events-section h2 {
            font-size: 16px;
            color: #1F2937;
            border-bottom: 2px solid #E5E7EB;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .event-card {
            border: 1px solid #E5E7EB;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .event-card.vacation {
            background-color: #DBEAFE;
            border-color: #93C5FD;
        }
        .event-card.leave {
            background-color: #FEF3C7;
            border-color: #FCD34D;
        }
        .event-card.absence {
            background-color: #FEE2E2;
            border-color: #FCA5A5;
        }
        .event-card.medical {
            background-color: #D1FAE5;
            border-color: #6EE7B7;
        }
        .event-header {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #111827;
        }
        .event-details {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .event-detail-row {
            display: table-row;
        }
        .event-detail-label {
            display: table-cell;
            font-weight: bold;
            color: #6B7280;
            width: 25%;
            padding: 3px 8px 3px 0;
        }
        .event-detail-value {
            display: table-cell;
            padding: 3px 0;
        }
        .event-notes {
            background-color: rgba(255, 255, 255, 0.6);
            border-radius: 3px;
            padding: 8px;
            margin-top: 8px;
            font-size: 10px;
        }
        .event-notes strong {
            display: block;
            margin-bottom: 4px;
            color: #6B7280;
        }
        .event-footer {
            font-size: 9px;
            color: #9CA3AF;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        .no-events {
            text-align: center;
            padding: 40px;
            color: #9CA3AF;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #E5E7EB;
            text-align: center;
            font-size: 9px;
            color: #9CA3AF;
        }
        .summary {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .summary h3 {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: #1F2937;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 8px;
            border-right: 1px solid #E5E7EB;
        }
        .summary-item:last-child {
            border-right: none;
        }
        .summary-item .label {
            font-size: 10px;
            color: #6B7280;
            display: block;
            margin-bottom: 4px;
        }
        .summary-item .value {
            font-size: 16px;
            font-weight: bold;
            color: #4F46E5;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de Eventos do Funcionário</h1>
        <p>Gerado em: {{ $generated_at }}</p>
    </div>

    <div class="employee-info">
        <table>
            <tr>
                <td>Nome:</td>
                <td>{{ $employee['name'] }}</td>
            </tr>
            @if($employee['cpf'])
            <tr>
                <td>CPF:</td>
                <td>{{ $employee['cpf'] }}</td>
            </tr>
            @endif
            @if($employee['position'])
            <tr>
                <td>Cargo:</td>
                <td>{{ $employee['position'] }}</td>
            </tr>
            @endif
            @if($employee['store'])
            <tr>
                <td>Loja:</td>
                <td>{{ $employee['store'] }}</td>
            </tr>
            @endif
            @if($employee['admission_date'])
            <tr>
                <td>Data de Admissão:</td>
                <td>{{ $employee['admission_date'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if($filters['event_types'] || $filters['start_date'] || $filters['end_date'])
    <div class="filters">
        <h3>Filtros Aplicados:</h3>
        @if($filters['event_types'])
            <p><strong>Tipos de Eventos:</strong> {{ implode(', ', $filters['event_types']) }}</p>
        @endif
        @if($filters['start_date'])
            <p><strong>Data Inicial:</strong> {{ $filters['start_date'] }}</p>
        @endif
        @if($filters['end_date'])
            <p><strong>Data Final:</strong> {{ $filters['end_date'] }}</p>
        @endif
    </div>
    @endif

    @if(count($events) > 0)
    <div class="summary">
        <h3>Resumo</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <span class="label">Total de Eventos</span>
                <span class="value">{{ count($events) }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Dias de Férias</span>
                <span class="value">{{ collect($events)->where('event_type', 'Férias')->sum('duration_in_days') }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Dias de Licença</span>
                <span class="value">{{ collect($events)->where('event_type', 'Licença')->sum('duration_in_days') }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Faltas</span>
                <span class="value">{{ collect($events)->where('event_type', 'Falta')->count() }}</span>
            </div>
        </div>
    </div>
    @endif

    <div class="events-section">
        <h2>Eventos Registrados ({{ count($events) }})</h2>

        @if(count($events) === 0)
            <div class="no-events">
                <p>Nenhum evento encontrado com os filtros selecionados.</p>
            </div>
        @else
            @foreach($events as $event)
                @php
                    $cardClass = '';
                    if ($event['event_type'] === 'Férias') {
                        $cardClass = 'vacation';
                    } elseif ($event['event_type'] === 'Licença') {
                        $cardClass = 'leave';
                    } elseif ($event['event_type'] === 'Falta') {
                        $cardClass = 'absence';
                    } elseif ($event['event_type'] === 'Atestado Médico') {
                        $cardClass = 'medical';
                    }
                @endphp
                <div class="event-card {{ $cardClass }}">
                    <div class="event-header">{{ $event['event_type'] }}</div>

                    <div class="event-details">
                        <div class="event-detail-row">
                            <div class="event-detail-label">Período:</div>
                            <div class="event-detail-value">{{ $event['period'] }}</div>
                        </div>
                        <div class="event-detail-row">
                            <div class="event-detail-label">Duração:</div>
                            <div class="event-detail-value">
                                {{ $event['duration_in_days'] }} {{ $event['duration_in_days'] === 1 ? 'dia' : 'dias' }}
                            </div>
                        </div>
                        <div class="event-detail-row">
                            <div class="event-detail-label">Documento:</div>
                            <div class="event-detail-value">
                                {{ $event['has_document'] ? 'Anexado' : 'Não anexado' }}
                            </div>
                        </div>
                    </div>

                    @if($event['notes'])
                    <div class="event-notes">
                        <strong>Observações:</strong>
                        {{ $event['notes'] }}
                    </div>
                    @endif

                    <div class="event-footer">
                        Registrado por {{ $event['created_by'] }} em {{ $event['created_at'] }}
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <div class="footer">
        <p>Documento gerado automaticamente pelo Sistema de Gestão de Funcionários</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>
