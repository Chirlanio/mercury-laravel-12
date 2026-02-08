<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório do Funcionário - {{ $employee['name'] }}</title>
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
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section h2 {
            font-size: 16px;
            color: #1F2937;
            border-bottom: 2px solid #E5E7EB;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .info-box {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .info-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-box td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .info-box td:first-child {
            font-weight: bold;
            width: 30%;
            color: #6B7280;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-active {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .badge-inactive {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        .badge-pcd {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .badge-apprentice {
            background-color: #EDE9FE;
            color: #5B21B6;
        }
        /* Contracts table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #4F46E5;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 10px;
        }
        .data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #E5E7EB;
            font-size: 10px;
        }
        .data-table tr:nth-child(even) {
            background-color: #F9FAFB;
        }
        /* Summary cards */
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
        /* Event cards */
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
        /* History timeline */
        .history-item {
            border-left: 3px solid #4F46E5;
            padding: 8px 12px;
            margin-bottom: 10px;
            background-color: #F9FAFB;
            border-radius: 0 5px 5px 0;
            page-break-inside: avoid;
        }
        .history-item .history-title {
            font-weight: bold;
            font-size: 12px;
            color: #1F2937;
            margin-bottom: 4px;
        }
        .history-item .history-desc {
            font-size: 10px;
            color: #4B5563;
            margin-bottom: 4px;
        }
        .history-item .history-values {
            font-size: 10px;
            color: #6B7280;
            margin-bottom: 4px;
        }
        .history-item .history-meta {
            font-size: 9px;
            color: #9CA3AF;
        }
        .no-records {
            text-align: center;
            padding: 20px;
            color: #9CA3AF;
            font-style: italic;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #E5E7EB;
            text-align: center;
            font-size: 9px;
            color: #9CA3AF;
        }
    </style>
</head>
<body>
    {{-- 1. Cabecalho --}}
    <div class="header">
        <h1>Relatório do Funcionário</h1>
        <p>Gerado em: {{ $generated_at }}</p>
    </div>

    {{-- 2. Dados Pessoais --}}
    <div class="section">
        <h2>Dados Pessoais</h2>
        <div class="info-box">
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
                @if($employee['birth_date'])
                <tr>
                    <td>Data de Nascimento:</td>
                    <td>
                        {{ $employee['birth_date'] }}
                        @if($employee['age'])
                            ({{ $employee['age'] }} anos)
                        @endif
                    </td>
                </tr>
                @endif
                <tr>
                    <td>Escolaridade:</td>
                    <td>{{ $employee['education_level'] }}</td>
                </tr>
                <tr>
                    <td>Gênero:</td>
                    <td>{{ $employee['gender'] }}</td>
                </tr>
                <tr>
                    <td>PcD:</td>
                    <td>
                        @if($employee['is_pcd'])
                            <span class="badge badge-pcd">Sim</span>
                        @else
                            Não
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Aprendiz:</td>
                    <td>
                        @if($employee['is_apprentice'])
                            <span class="badge badge-apprentice">Sim</span>
                        @else
                            Não
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- 3. Dados Profissionais --}}
    <div class="section">
        <h2>Dados Profissionais</h2>
        <div class="info-box">
            <table>
                <tr>
                    <td>Cargo:</td>
                    <td>{{ $employee['position'] }}</td>
                </tr>
                <tr>
                    <td>Nível:</td>
                    <td>{{ $employee['level'] }}</td>
                </tr>
                <tr>
                    <td>Loja:</td>
                    <td>{{ $employee['store'] }}</td>
                </tr>
                @if($employee['admission_date'])
                <tr>
                    <td>Data de Admissão:</td>
                    <td>{{ $employee['admission_date'] }}</td>
                </tr>
                @endif
                @if($employee['dismissal_date'])
                <tr>
                    <td>Data de Demissão:</td>
                    <td>{{ $employee['dismissal_date'] }}</td>
                </tr>
                @endif
                <tr>
                    <td>Tempo de Serviço:</td>
                    <td>
                        @if($employee['years_of_service'] !== null)
                            {{ $employee['years_of_service'] }} {{ $employee['years_of_service'] === 1 ? 'ano' : 'anos' }}
                        @else
                            Não informado
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Status:</td>
                    <td>
                        <span class="badge {{ $employee['is_active'] ? 'badge-active' : 'badge-inactive' }}">
                            {{ $employee['status'] }}
                        </span>
                    </td>
                </tr>
                @if($employee['site_coupon'])
                <tr>
                    <td>Cupom Site:</td>
                    <td>{{ $employee['site_coupon'] }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    {{-- 4. Historico de Contratos --}}
    <div class="section">
        <h2>Histórico de Contratos ({{ count($contracts) }})</h2>
        @if(count($contracts) > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cargo</th>
                        <th>Loja</th>
                        <th>Movimentação</th>
                        <th>Período</th>
                        <th>Duração</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contracts as $contract)
                    <tr>
                        <td>{{ $contract['position'] }}</td>
                        <td>{{ $contract['store'] }}</td>
                        <td>{{ $contract['movement_type'] }}</td>
                        <td>{{ $contract['date_range'] }}</td>
                        <td>{{ $contract['duration'] }}</td>
                        <td>{{ $contract['status_label'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-records">Nenhum contrato registrado.</div>
        @endif
    </div>

    {{-- 5. Resumo de Eventos --}}
    @if(count($events) > 0)
    <div class="section">
        <div class="summary">
            <h3>Resumo de Eventos</h3>
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
                <div class="summary-item">
                    <span class="label">Atestados</span>
                    <span class="value">{{ collect($events)->where('event_type', 'Atestado Médico')->count() }}</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- 6. Eventos Detalhados --}}
    <div class="section">
        <h2>Eventos Registrados ({{ count($events) }})</h2>
        @if(count($events) > 0)
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
        @else
            <div class="no-records">Nenhum evento registrado.</div>
        @endif
    </div>

    {{-- 7. Historico de Mudancas --}}
    <div class="section">
        <h2>Histórico de Mudanças ({{ count($histories) }})</h2>
        @if(count($histories) > 0)
            @foreach($histories as $history)
                <div class="history-item">
                    <div class="history-title">{{ $history['event_type_label'] }} - {{ $history['title'] }}</div>
                    @if($history['description'])
                        <div class="history-desc">{{ $history['description'] }}</div>
                    @endif
                    @if($history['old_value'] || $history['new_value'])
                        <div class="history-values">
                            @if($history['old_value'])
                                De: {{ $history['old_value'] }}
                            @endif
                            @if($history['old_value'] && $history['new_value'])
                                &rarr;
                            @endif
                            @if($history['new_value'])
                                Para: {{ $history['new_value'] }}
                            @endif
                        </div>
                    @endif
                    <div class="history-meta">
                        {{ $history['event_date'] }} - por {{ $history['created_by'] }}
                    </div>
                </div>
            @endforeach
        @else
            <div class="no-records">Nenhuma mudança registrada.</div>
        @endif
    </div>

    {{-- Rodape --}}
    <div class="footer">
        <p>Documento gerado automaticamente pelo Sistema de Gestão de Funcionários</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>
