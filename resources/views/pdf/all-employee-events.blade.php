<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Eventos - Todos os Funcionários</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 15px;
        }
        .header {
            border-bottom: 3px solid #4F46E5;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 18px;
            color: #4F46E5;
        }
        .header p {
            margin: 2px 0;
            color: #666;
            font-size: 9px;
        }
        .filters {
            background-color: #EEF2FF;
            border: 1px solid #C7D2FE;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 15px;
        }
        .filters h3 {
            margin: 0 0 6px 0;
            font-size: 11px;
            color: #4F46E5;
        }
        .filters p {
            margin: 2px 0;
            font-size: 9px;
        }
        .filters strong {
            color: #4338CA;
        }
        .summary {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .summary h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #1F2937;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 6px;
            border-right: 1px solid #E5E7EB;
        }
        .summary-item:last-child {
            border-right: none;
        }
        .summary-item .label {
            font-size: 8px;
            color: #6B7280;
            display: block;
            margin-bottom: 3px;
        }
        .summary-item .value {
            font-size: 14px;
            font-weight: bold;
            color: #4F46E5;
        }
        .employee-section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .employee-info {
            background-color: #4F46E5;
            color: white;
            padding: 6px 10px;
            margin-bottom: 5px;
            border-radius: 3px;
        }
        .employee-info strong {
            font-size: 11px;
        }
        .employee-info span {
            font-size: 8px;
            opacity: 0.9;
        }
        table.events-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8px;
        }
        table.events-table thead {
            background-color: #F3F4F6;
        }
        table.events-table th {
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #D1D5DB;
            color: #1F2937;
            font-size: 8px;
        }
        table.events-table td {
            padding: 5px 4px;
            border: 1px solid #E5E7EB;
            vertical-align: top;
        }
        table.events-table tbody tr:nth-child(even) {
            background-color: #F9FAFB;
        }
        table.events-table tbody tr:hover {
            background-color: #F3F4F6;
        }
        .event-type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }
        .badge-vacation {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .badge-leave {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .badge-absence {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        .badge-medical {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .has-document {
            color: #059669;
            font-weight: bold;
        }
        .no-document {
            color: #9CA3AF;
        }
        .notes-cell {
            font-size: 7px;
            color: #6B7280;
            max-width: 150px;
        }
        .employee-totals {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            padding: 5px 8px;
            margin-bottom: 8px;
            border-radius: 3px;
            font-size: 8px;
        }
        .employee-totals strong {
            color: #1F2937;
        }
        .employee-header h2 {
            margin: 0;
            font-size: 13px;
        }
        .employee-header p {
            margin: 3px 0 0 0;
            font-size: 9px;
            opacity: 0.9;
        }
        .employee-totals {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            background-color: white;
            border: 1px solid #E5E7EB;
            border-radius: 3px;
        }
        .employee-totals-item {
            display: table-cell;
            text-align: center;
            padding: 5px;
            border-right: 1px solid #E5E7EB;
        }
        .employee-totals-item:last-child {
            border-right: none;
        }
        .employee-totals-item .label {
            font-size: 7px;
            color: #6B7280;
            display: block;
            margin-bottom: 2px;
        }
        .employee-totals-item .value {
            font-size: 11px;
            font-weight: bold;
            color: #1F2937;
        }
        .event-card {
            border: 1px solid #E5E7EB;
            border-radius: 3px;
            padding: 8px;
            margin-bottom: 8px;
            page-break-inside: avoid;
            background-color: white;
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
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #111827;
        }
        .event-details {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .event-detail-row {
            display: table-row;
        }
        .event-detail-label {
            display: table-cell;
            font-weight: bold;
            color: #6B7280;
            width: 30%;
            padding: 2px 6px 2px 0;
            font-size: 9px;
        }
        .event-detail-value {
            display: table-cell;
            padding: 2px 0;
            font-size: 9px;
        }
        .event-notes {
            background-color: rgba(255, 255, 255, 0.6);
            border-radius: 2px;
            padding: 5px;
            margin-top: 5px;
            font-size: 8px;
        }
        .event-notes strong {
            display: block;
            margin-bottom: 2px;
            color: #6B7280;
        }
        .event-footer {
            font-size: 7px;
            color: #9CA3AF;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        .no-events {
            text-align: center;
            padding: 20px;
            color: #9CA3AF;
            font-size: 10px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #E5E7EB;
            text-align: center;
            font-size: 8px;
            color: #9CA3AF;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório Consolidado de Eventos</h1>
        <p>Gerado em: {{ $generated_at }}</p>
    </div>

    @if($filters['event_types'] || $filters['stores'] || $filters['start_date'] || $filters['end_date'])
    <div class="filters">
        <h3>Filtros Aplicados:</h3>
        @if($filters['event_types'])
            <p><strong>Tipos de Eventos:</strong> {{ implode(', ', $filters['event_types']) }}</p>
        @endif
        @if($filters['stores'])
            <p><strong>Lojas:</strong> {{ implode(', ', $filters['stores']) }}</p>
        @endif
        @if($filters['start_date'])
            <p><strong>Data Inicial:</strong> {{ $filters['start_date'] }}</p>
        @endif
        @if($filters['end_date'])
            <p><strong>Data Final:</strong> {{ $filters['end_date'] }}</p>
        @endif
    </div>
    @endif

    <div class="summary">
        <h3>Resumo Geral</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <span class="label">Funcionários</span>
                <span class="value">{{ $summary['total_employees'] }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Total de Eventos</span>
                <span class="value">{{ $summary['total_events'] }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Dias de Férias</span>
                <span class="value">{{ $summary['total_vacation_days'] }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Dias de Licença</span>
                <span class="value">{{ $summary['total_leave_days'] }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Total de Faltas</span>
                <span class="value">{{ $summary['total_absences'] }}</span>
            </div>
        </div>
    </div>

    @if(count($employees_data) === 0)
        <div class="no-events">
            <p>Nenhum evento encontrado com os filtros selecionados.</p>
        </div>
    @else
        @foreach($employees_data as $employeeData)
            <div class="employee-section">
                <div class="employee-info">
                    <strong>{{ $employeeData['employee']['name'] }}</strong>
                    <span>
                        @if($employeeData['employee']['position'])
                            {{ $employeeData['employee']['position'] }}
                        @endif
                        @if($employeeData['employee']['store'])
                            • {{ $employeeData['employee']['store'] }}
                        @endif
                        @if($employeeData['employee']['cpf'])
                            • CPF: {{ $employeeData['employee']['cpf'] }}
                        @endif
                    </span>
                </div>

                <div class="employee-totals">
                    <strong>Totais:</strong>
                    {{ $employeeData['totals']['total_events'] }} eventos •
                    {{ $employeeData['totals']['vacation_days'] }} dias de férias •
                    {{ $employeeData['totals']['leave_days'] }} dias de licença •
                    {{ $employeeData['totals']['absences'] }} faltas
                </div>

                <table class="events-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Tipo</th>
                            <th style="width: 20%;">Período</th>
                            <th style="width: 10%;">Duração</th>
                            <th style="width: 10%;">Documento</th>
                            <th style="width: 30%;">Observações</th>
                            <th style="width: 15%;">Registrado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employeeData['events'] as $event)
                            <tr>
                                <td>
                                    @php
                                        $badgeClass = '';
                                        if ($event['event_type'] === 'Férias') {
                                            $badgeClass = 'badge-vacation';
                                        } elseif ($event['event_type'] === 'Licença') {
                                            $badgeClass = 'badge-leave';
                                        } elseif ($event['event_type'] === 'Falta') {
                                            $badgeClass = 'badge-absence';
                                        } elseif ($event['event_type'] === 'Atestado Médico') {
                                            $badgeClass = 'badge-medical';
                                        }
                                    @endphp
                                    <span class="event-type-badge {{ $badgeClass }}">
                                        {{ $event['event_type'] }}
                                    </span>
                                </td>
                                <td>{{ $event['period'] }}</td>
                                <td style="text-align: center;">
                                    {{ $event['duration_in_days'] }} {{ $event['duration_in_days'] === 1 ? 'dia' : 'dias' }}
                                </td>
                                <td style="text-align: center;">
                                    @if($event['has_document'])
                                        <span class="has-document">✓ Sim</span>
                                    @else
                                        <span class="no-document">- Não</span>
                                    @endif
                                </td>
                                <td class="notes-cell">
                                    {{ $event['notes'] ?: '-' }}
                                </td>
                                <td style="font-size: 7px; color: #6B7280;">
                                    {{ $event['created_by'] }}<br>
                                    {{ $event['created_at'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    <div class="footer">
        <p>Documento gerado automaticamente pelo Sistema de Gestão de Funcionários</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>
