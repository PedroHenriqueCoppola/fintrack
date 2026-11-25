<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 36px 32px 48px; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1a1726; }
        .band { width: 100%; border-bottom: 1px solid #e0daee; margin: 0 0 16px; }
        .band td { padding: 0 0 8px; vertical-align: middle; }
        .mark { display: inline-block; width: 16px; height: 16px; background: #7c4fd6; color: #fff; font-size: 10px; font-weight: 700; text-align: center; line-height: 16px; }
        .brand { font-size: 12px; font-weight: 700; }
        .generated { color: #6f6a84; font-size: 9px; text-align: right; }
        h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .period { margin: 4px 0 0; font-size: 10px; color: #5f5a72; }
        .chips { margin: 8px 0 0; }
        .chip { display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 8px; background: #ece4fb; color: #5a2fb0; font-size: 9px; }
        .note { margin: 6px 0 0; color: #5f5a72; }
        h2 { margin: 16px 0 6px; font-size: 12px; }
        .kpis { width: 100%; margin-top: 12px; border-collapse: separate; border-spacing: 8px 0; }
        .kpis td { width: 25%; padding: 8px 10px; border: 1px solid #e0daee; background: #fff; vertical-align: top; }
        .kpis .label { display: block; color: #5f5a72; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpis .value { display: block; margin-top: 4px; font-size: 13px; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { padding: 4px 6px; border-bottom: 1px solid #e0daee; vertical-align: middle; font-weight: 400; }
        table.data thead { display: table-header-group; }
        table.data thead th { background: #f4f0fb; color: #5f5a72; font-size: 8px; font-weight: 700; text-align: left; text-transform: uppercase; letter-spacing: 0.04em; }
        table.data tbody tr:nth-child(even) td { background: #faf8fd; }
        table.data td.num, table.data th.num { text-align: right; }
        table.data tfoot th, table.data tfoot td { font-weight: 700; border-top: 1.5pt solid #1a1726; background: #fff; }
        .neg { color: #c23b43; }
        .pos { color: #2a7a62; }
        .muted { color: #6f6a84; }
        .share { display: inline-block; white-space: nowrap; }
        .bar { display: inline-block; width: 46px; height: 6px; margin-right: 6px; background: #f4f0fb; vertical-align: middle; }
        .bar span { display: block; height: 6px; background: #7c4fd6; }
        .share-text { display: inline-block; width: 48px; text-align: right; vertical-align: middle; }
    </style>
</head>
<body>
    <table class="band">
        <tr>
            <td><span class="mark">F</span> <span class="brand">Fintrack</span></td>
            <td class="generated">Gerado em {{ $formatter->generatedAt($report->generatedAt) }}</td>
        </tr>
    </table>
    <h1>{{ $report->title }}</h1>
    <p class="period">{{ $report->periodDetail }}</p>
    @if ($report->filters !== [])
        <p class="chips">
            @foreach ($report->filters as $filter)
                <span class="chip">{{ $filter['label'] }}: {{ $filter['value'] }}</span>
            @endforeach
        </p>
    @endif
    @if ($report->note)
        <p class="note">{{ $report->note }}</p>
    @endif

    @foreach ($report->sections as $section)
        @if (!empty($section['title']))
            <h2>{{ $section['title'] }}</h2>
        @endif
        @if (($section['kind'] ?? 'table') === 'kpis')
            <table class="kpis">
                <tr>
                    @foreach ($section['items'] as $item)
                        <td>
                            <span class="label">{{ $item['label'] }}</span>
                            <span class="value {{ $item['tone'] === 'negative' ? 'neg' : ($item['tone'] === 'positive' ? 'pos' : ($item['tone'] === 'muted' ? 'muted' : '')) }}">{{ $item['text'] }}</span>
                        </td>
                    @endforeach
                </tr>
            </table>
        @else
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($section['columns'] as $column)
                            <th class="{{ in_array($column['type'], ['money', 'percent', 'integer'], true) ? 'num' : '' }}" scope="col">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            @foreach ($section['columns'] as $column)
                                @php $cell = $row[$column['key']]; @endphp
                                <td class="{{ in_array($column['type'], ['money', 'percent', 'integer'], true) ? 'num' : '' }} {{ $cell['tone'] === 'negative' ? 'neg' : ($cell['tone'] === 'positive' ? 'pos' : ($cell['tone'] === 'muted' ? 'muted' : '')) }}">
                                    @if (!empty($cell['bar']))
                                        <span class="share"><span class="bar"><span style="width: {{ $cell['bar'] }}"></span></span><span class="share-text">{{ $cell['text'] }}</span></span>
                                    @else
                                        {{ $cell['text'] }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if (!empty($section['totals']))
                    <tfoot>
                        <tr>
                            @foreach ($section['columns'] as $index => $column)
                                @php $cell = $section['totals'][$column['key']]; @endphp
                                @if ($index === 0)
                                    <th scope="row">{{ $cell['text'] }}</th>
                                @else
                                    <td class="{{ in_array($column['type'], ['money', 'percent', 'integer'], true) ? 'num' : '' }} {{ $cell['tone'] === 'negative' ? 'neg' : ($cell['tone'] === 'positive' ? 'pos' : ($cell['tone'] === 'muted' ? 'muted' : '')) }}">
                                        @if (!empty($cell['bar']))
                                            <span class="share"><span class="bar"><span style="width: {{ $cell['bar'] }}"></span></span><span class="share-text">{{ $cell['text'] }}</span></span>
                                        @else
                                            {{ $cell['text'] }}
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        @endif
    @endforeach
</body>
</html>
