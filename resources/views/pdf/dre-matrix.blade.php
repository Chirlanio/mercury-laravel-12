<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DRE Gerencial</title>
    <style>
        @page { margin: 10mm 8mm; size: A4 landscape; }
        body { font-family: Arial, sans-serif; font-size: 8px; color: #111; margin: 0; }
        h1 { font-size: 14px; color: #4338ca; margin: 0 0 2px; }
        .subtitle { font-size: 8px; color: #6b7280; margin-bottom: 8px; }
        .header { border-bottom: 2px solid #4338ca; padding-bottom: 6px; margin-bottom: 8px; }

        .filters {
            background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 4px;
            padding: 5px 8px; margin-bottom: 8px; font-size: 7px;
        }
        .filters strong { color: #4338ca; text-transform: uppercase; font-size: 7px; margin-right: 4px; }
        .chip {
            display: inline-block; background: #fff; border: 1px solid #c7d2fe;
            border-radius: 10px; padding: 1px 6px; margin: 0 2px;
            font-size: 7px; color: #4338ca;
        }

        .kpis { margin-bottom: 8px; width: 100%; border-collapse: collapse; }
        .kpis th { background: #f3f4f6; padding: 4px 6px; font-size: 7px;
                   text-align: left; border: 1px solid #e5e7eb; text-transform: uppercase; }
        .kpis td { padding: 4px 6px; font-size: 8px; border: 1px solid #e5e7eb; }
        .kpis td.value { font-weight: bold; text-align: right; font-size: 9px; color: #111827; }

        table.matrix { width: 100%; border-collapse: collapse; }
        table.matrix th {
            background: #4338ca; color: #fff; padding: 3px 4px;
            font-size: 6.5px; text-align: right; text-transform: uppercase;
        }
        table.matrix th:first-child, table.matrix th.line { text-align: left; }
        table.matrix td { padding: 2px 4px; border-bottom: 1px solid #f3f4f6;
                          font-size: 7.5px; text-align: right; }
        table.matrix td.line { text-align: left; }
        table.matrix td.code { font-family: monospace; font-size: 7px; color: #6b7280; }
        table.matrix tr.subtotal { background: #f3f4f6; font-weight: bold; }
        table.matrix tr:nth-child(even):not(.subtotal) { background: #fafafa; }
        .neg { color: #b91c1c; }

        .footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            font-size: 6.5px; color: #9ca3af; text-align: center;
            border-top: 1px solid #e5e7eb; padding-top: 4px;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>DRE Gerencial</h1>
    <div class="subtitle">
        {{ $filter['scope'] ?? 'general' }} · {{ $filter['start_date'] ?? '' }} a {{ $filter['end_date'] ?? '' }}
        · Gerado em {{ now()->format('d/m/Y H:i') }}
        @if($generatedByName) · {{ $generatedByName }} @endif
    </div>
</div>

<div class="filters">
    <strong>Filtros</strong>
    <span class="chip">Escopo: {{ $filter['scope'] ?? 'general' }}</span>
    @if(!empty($filter['store_ids']))
        <span class="chip">Lojas: {{ count($filter['store_ids']) }}</span>
    @endif
    @if(!empty($filter['network_ids']))
        <span class="chip">Redes: {{ count($filter['network_ids']) }}</span>
    @endif
    <span class="chip">Versão orçamento: {{ $filter['budget_version'] ?? 'padrão' }}</span>
    @if(!empty($filter['compare_previous_year']))
        <span class="chip">vs. Ano Anterior</span>
    @endif
</div>

<table class="kpis">
    <thead>
        <tr>
            <th>Indicador</th>
            <th style="text-align:right;">Realizado</th>
            <th style="text-align:right;">Orçado</th>
            <th style="text-align:right;">Ano Anterior</th>
        </tr>
    </thead>
    <tbody>
        @php
            $kpiLabels = [
                'faturamento_liquido' => 'Faturamento Líquido',
                'ebitda' => 'EBITDA',
                'margem_liquida' => 'Margem Líquida',
                'nao_classificado' => 'Não-classificado',
            ];
        @endphp
        @foreach ($kpiLabels as $key => $label)
            @php
                $k = $kpis[$key] ?? ['actual' => 0, 'budget' => 0, 'previous_year' => 0];
                $isPct = $key === 'margem_liquida';
                $mult = $isPct ? 100 : 1;
                $suffix = $isPct ? '%' : '';
            @endphp
            <tr>
                <td>{{ $label }}</td>
                <td class="value">{{ number_format((float) ($k['actual'] ?? 0) * $mult, 2, ',', '.') }}{{ $suffix }}</td>
                <td class="value">{{ number_format((float) ($k['budget'] ?? 0) * $mult, 2, ',', '.') }}{{ $suffix }}</td>
                <td class="value">{{ number_format((float) ($k['previous_year'] ?? 0) * $mult, 2, ',', '.') }}{{ $suffix }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="matrix">
    <thead>
        <tr>
            <th class="line" style="width:28%;">Linha</th>
            @foreach ($yearMonths as $ym)
                <th>{{ substr($ym, 5, 2) }}/{{ substr($ym, 0, 4) }}</th>
            @endforeach
            <th>Total Real.</th>
            <th>Total Orç.</th>
            <th>%</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($matrix['lines'] ?? [] as $line)
            <tr class="{{ !empty($line['is_subtotal']) ? 'subtotal' : '' }}">
                <td class="line">
                    <span class="code">{{ $line['code'] ?? '' }}</span>
                    {{ $line['level_1'] ?? $line['name'] ?? '' }}
                </td>
                @foreach ($yearMonths as $ym)
                    @php
                        $v = (float) ($line['months'][$ym]['actual'] ?? 0);
                    @endphp
                    <td class="{{ $v < 0 ? 'neg' : '' }}">
                        {{ $v == 0 ? '—' : number_format($v, 0, ',', '.') }}
                    </td>
                @endforeach
                @php
                    $tA = (float) ($line['totals']['actual'] ?? 0);
                    $tB = (float) ($line['totals']['budget'] ?? 0);
                    $pct = abs($tB) > 0.0001 ? ($tA / $tB) * 100 : 0;
                @endphp
                <td class="{{ $tA < 0 ? 'neg' : '' }}">{{ number_format($tA, 0, ',', '.') }}</td>
                <td>{{ number_format($tB, 0, ',', '.') }}</td>
                <td>{{ number_format($pct, 1, ',', '.') }}%</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    Grupo Meia Sola — DRE Gerencial · gerado por Mercury · valores em R$
</div>
</body>
</html>
