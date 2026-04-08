<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            margin: 2mm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .page {
            width: 100%;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        table.labels {
            width: 100%;
            border-collapse: collapse;
        }

        td.label {
            width: {{ $preset['width'] }}mm;
            height: {{ $preset['height'] }}mm;
            padding: 1mm;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
        }

        td.gap {
            width: {{ $preset['gap'] }}mm;
        }

        .label-reference {
            font-size: {{ max(6, min(10, $preset['width'] * 0.16)) }}pt;
            font-weight: bold;
            line-height: 1.2;
            overflow: hidden;
        }

        .label-description {
            font-size: {{ max(5, min(8, $preset['width'] * 0.12)) }}pt;
            line-height: 1.1;
            overflow: hidden;
            max-height: {{ $preset['height'] * 0.2 }}mm;
        }

        .label-size {
            font-size: {{ max(5, min(8, $preset['width'] * 0.13)) }}pt;
            margin: 0.5mm 0;
        }

        .label-barcode img {
            max-width: {{ $preset['width'] - 4 }}mm;
            height: {{ max(8, $preset['height'] * 0.3) }}mm;
        }

        .label-barcode-number {
            font-size: {{ max(5, min(7, $preset['width'] * 0.11)) }}pt;
            letter-spacing: 0.5pt;
        }

        .label-price {
            font-size: {{ max(6, min(9, $preset['width'] * 0.14)) }}pt;
            font-weight: bold;
            margin-top: 0.5mm;
        }

        .no-barcode {
            font-size: 6pt;
            color: #999;
            padding: 2mm 0;
        }
    </style>
</head>
<body>
    @php
        $chunks = array_chunk($labels, $labelsPerPage);
        $columns = (int) $preset['columns'];
    @endphp

    @foreach ($chunks as $pageLabels)
        <div class="page">
            <table class="labels">
                @foreach (array_chunk($pageLabels, $columns) as $row)
                    <tr>
                        @foreach ($row as $i => $label)
                            @if ($i > 0 && $preset['gap'] > 0)
                                <td class="gap"></td>
                            @endif
                            <td class="label">
                                <div class="label-reference">{{ $label['reference'] }}</div>
                                <div class="label-description">{{ \Illuminate\Support\Str::limit($label['description'], 40) }}</div>
                                <div class="label-size">Tam: {{ $label['size_name'] }}</div>
                                @if ($label['barcode_image'])
                                    <div class="label-barcode">
                                        <img src="{{ $label['barcode_image'] }}" alt="barcode">
                                    </div>
                                    <div class="label-barcode-number">{{ $label['barcode_number'] }}</div>
                                @else
                                    <div class="no-barcode">Sem código de barras</div>
                                @endif
                                @if ($label['sale_price'])
                                    <div class="label-price">{{ $label['sale_price'] }}</div>
                                @endif
                            </td>
                        @endforeach

                        {{-- Fill remaining cells if row is incomplete --}}
                        @for ($j = count($row); $j < $columns; $j++)
                            @if ($j > 0 && $preset['gap'] > 0)
                                <td class="gap"></td>
                            @endif
                            <td class="label"></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
