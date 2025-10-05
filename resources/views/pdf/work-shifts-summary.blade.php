<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumo de Jornadas de Trabalho</title>
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
        .store-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .store-header {
            background-color: #4F46E5;
            color: white;
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        .store-header h2 {
            margin: 0;
            font-size: 13px;
        }
        .store-header p {
            margin: 3px 0 0 0;
            font-size: 9px;
            opacity: 0.9;
        }
        .store-totals {
            background-color: #EEF2FF;
            border: 1px solid #C7D2FE;
            padding: 6px 10px;
            margin-bottom: 10px;
            border-radius: 3px;
            font-size: 9px;
        }
        .store-totals strong {
            color: #4338CA;
        }
        .employee-section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .employee-header {
            background-color: #F3F4F6;
            padding: 6px 10px;
            margin-bottom: 5px;
            border-left: 3px solid #6366F1;
        }
        .employee-header strong {
            font-size: 11px;
            color: #1F2937;
        }
        .employee-header span {
            font-size: 9px;
            color: #6B7280;
            margin-left: 8px;
        }
        table.shifts-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8px;
        }
        table.shifts-table thead {
            background-color: #F9FAFB;
        }
        table.shifts-table th {
            padding: 5px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #D1D5DB;
            color: #1F2937;
            font-size: 8px;
        }
        table.shifts-table td {
            padding: 4px;
            border: 1px solid #E5E7EB;
            vertical-align: top;
        }
        table.shifts-table tbody tr:nth-child(even) {
            background-color: #F9FAFB;
        }
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }
        .badge-abertura {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .badge-fechamento {
            background-color: #E9D5FF;
            color: #6B21A8;
        }
        .badge-integral {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .badge-compensar {
            background-color: #FED7AA;
            color: #9A3412;
        }
        .employee-total {
            background-color: #FEF3C7;
            padding: 4px 8px;
            margin-top: 5px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            color: #92400E;
            text-align: right;
        }
        .no-data {
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
        <h1>Resumo de Jornadas de Trabalho</h1>
        <p>Relatório consolidado por loja e funcionário</p>
        <p>Gerado em: {{ $generated_at }}</p>
    </div>

    @if($filters['stores'] || $filters['employees'] || $filters['types'] || $filters['start_date'] || $filters['end_date'])
    <div class="filters">
        <h3>Filtros Aplicados:</h3>
        @if($filters['stores'])
            <p><strong>Lojas:</strong> {{ implode(', ', $filters['stores']) }}</p>
        @endif
        @if($filters['employees'])
            <p><strong>Funcionários:</strong> {{ implode(', ', $filters['employees']) }}</p>
        @endif
        @if($filters['types'])
            <p><strong>Tipos de Jornada:</strong> {{ implode(', ', $filters['types']) }}</p>
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
                <span class="label">Lojas</span>
                <span class="value">{{ $summary['total_stores'] }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Funcionários</span>
                <span class="value">{{ $summary['total_employees'] }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Total de Jornadas</span>
                <span class="value">{{ $summary['total_shifts'] }}</span>
            </div>
            <div class="summary-item">
                <span class="label">Total de Horas</span>
                <span class="value">{{ $summary['total_hours'] }}</span>
            </div>
        </div>
    </div>

    @if(count($stores) === 0)
        <div class="no-data">
            <p>Nenhuma jornada encontrada com os filtros selecionados.</p>
        </div>
    @else
        @foreach($stores as $store)
            <div class="store-section">
                <div class="store-header">
                    <h2>{{ $store['name'] }}</h2>
                    <p>Código: {{ $store['code'] }}</p>
                </div>

                <div class="store-totals">
                    <strong>Totais da Loja:</strong>
                    {{ $store['total_shifts'] }} jornadas •
                    {{ $store['total_hours'] }} horas trabalhadas •
                    {{ count($store['employees']) }} funcionários
                </div>

                @foreach($store['employees'] as $employee)
                    <div class="employee-section">
                        <div class="employee-header">
                            <strong>{{ $employee['name'] }}</strong>
                            <span>{{ $employee['total_shifts'] }} jornadas • {{ $employee['total_hours'] }} horas</span>
                        </div>

                        <table class="shifts-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Data</th>
                                    <th style="width: 12%;">Início</th>
                                    <th style="width: 12%;">Término</th>
                                    <th style="width: 12%;">Duração</th>
                                    <th style="width: 20%;">Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($employee['shifts'] as $shift)
                                    <tr>
                                        <td>{{ $shift['date'] }}</td>
                                        <td>{{ $shift['start_time'] }}</td>
                                        <td>{{ $shift['end_time'] }}</td>
                                        <td>
                                            @php
                                                $hours = floor($shift['duration_minutes'] / 60);
                                                $mins = $shift['duration_minutes'] % 60;
                                                $prefix = isset($shift['is_compensation']) && $shift['is_compensation'] ? '-' : '';
                                                echo $prefix . sprintf('%02d:%02d', $hours, $mins);
                                            @endphp
                                        </td>
                                        <td>
                                            @php
                                                $badgeClass = '';
                                                if ($shift['type'] === 'Abertura') {
                                                    $badgeClass = 'badge-abertura';
                                                } elseif ($shift['type'] === 'Fechamento') {
                                                    $badgeClass = 'badge-fechamento';
                                                } elseif ($shift['type'] === 'Integral') {
                                                    $badgeClass = 'badge-integral';
                                                } elseif ($shift['type'] === 'Compensar') {
                                                    $badgeClass = 'badge-compensar';
                                                }
                                            @endphp
                                            <span class="type-badge {{ $badgeClass }}">
                                                {{ $shift['type'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="employee-total">
                            Total do Funcionário: {{ $employee['total_hours'] }} horas ({{ $employee['total_shifts'] }} jornadas)
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    <div class="footer">
        <p>Documento gerado automaticamente pelo Sistema de Gestão de Jornadas de Trabalho</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>
