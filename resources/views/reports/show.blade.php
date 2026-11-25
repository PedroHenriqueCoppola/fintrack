<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 28px 32px 46px; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1a1726; }
        .brand { margin: 0; font-size: 11px; font-weight: 700; color: #5a2fb0; }
        h1 { margin: 2px 0 0; font-size: 16px; font-weight: 700; }
        .meta { margin: 2px 0 0; color: #5f5a72; }
        .ok { color: #2a7a62; }
        h2 { margin: 16px 0 6px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e0daee; vertical-align: middle; }
        thead { display: table-header-group; }
        thead th, tbody tr:nth-child(even) td, .bar { background: #ece4fb; }
        th { font-size: 9px; font-weight: 700; text-align: left; color: #5f5a72; }
        td.num, th.num { text-align: right; }
        tr.total td { font-weight: 700; }
        .neg { color: #c23b43; }
        .bar { display: inline-block; width: 46px; height: 6px; margin-right: 6px; vertical-align: middle; }
        .bar span { display: block; height: 6px; background: #7c4fd6; }
    </style>
</head>
<body>
    <p class="brand">Fintrack</p>
    <h1>{{ $report->title }}</h1>
    <p class="meta">{{ $report->periodLabel }}</p>
    <p class="meta">Filtros: {{ $report->filtersSummary }}</p>
    <p class="meta">Gerado em {{ $formatter->generatedAt($report->generatedAt) }}</p>
    @if ($report->note)
        <p class="meta">{{ $report->note }}</p>
    @endif

    @foreach ($report->sections as $section)
        @if (!empty($section['title']))
            <h2>{{ $section['title'] }}</h2>
        @endif
        <table>
            <thead>
                <tr>
                    @foreach ($section['columns'] as $column)
                        <th class="{{ $column['type'] === 'text' || $column['type'] === 'status' ? '' : 'num' }}" scope="col">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($section['rows'] as $row)
                    <tr>
                        @foreach ($section['columns'] as $column)
                            @php $value = $row[$column['key']] ?? null; @endphp
                            <td class="{{ $column['type'] === 'text' || $column['type'] === 'status' ? '' : 'num' }}{{ $formatter->isNegative($value, $column) ? ' neg' : '' }}">
                                @if (($section['bar_column'] ?? null) === $column['key'])
                                    <span class="bar"><span style="width: {{ $formatter->barWidth($value) }}"></span></span>
                                @endif
                                {{ $formatter->cell($value, $column) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if (!empty($section['totals']))
                    <tr class="total">
                        @foreach ($section['columns'] as $column)
                            @php $value = $section['totals'][$column['key']] ?? null; @endphp
                            <td class="{{ $column['type'] === 'text' || $column['type'] === 'status' ? '' : 'num' }}{{ $formatter->isNegative($value, $column) ? ' neg' : '' }}">
                                @if (($section['bar_column'] ?? null) === $column['key'])
                                    <span class="bar"><span style="width: {{ $formatter->barWidth($value) }}"></span></span>
                                @endif
                                {{ $formatter->cell($value, $column) }}
                            </td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    @endforeach

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) {
                $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
                $text = 'Página '.$pageNumber.' de '.$pageCount;
                $size = 9;
                $width = $fontMetrics->getTextWidth($text, $font, $size);
                $canvas->text(($canvas->get_width() - $width) / 2, $canvas->get_height() - 24, $text, $font, $size, [0.373, 0.353, 0.447]);
            });
        }
    </script>
</body>
</html>
