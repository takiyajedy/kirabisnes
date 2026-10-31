{{--
    One template for every report: a title, some figures, then one or more
    tables. Each report hands over the same shape, so there is no second
    layout to keep in step with this one.

    Always light, and written in the language chosen at export time, which
    the service sets before rendering (PLAN.md 3.6 and phase 5).
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 32px 36px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.5;
            color: #18181b;
            background: #ffffff;
        }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .muted { color: #71717a; }
        .right { text-align: right; }
        .strong { font-weight: bold; }
        .nowrap { white-space: nowrap; }

        .title { font-size: 20px; font-weight: bold; margin: 0 0 2px; letter-spacing: -0.3px; }
        .business-name { font-size: 12px; font-weight: bold; }

        .label {
            font-size: 8px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #71717a;
        }

        .figures { margin-top: 20px; }
        .figures td {
            width: 25%;
            border: 1px solid #e4e4e7;
            border-radius: 4px;
            padding: 8px 10px;
            background: #fafafa;
        }
        .figure-value { font-size: 14px; font-weight: bold; }

        .section { margin-top: 22px; }
        .section h2 {
            font-size: 11px;
            margin: 0 0 6px;
        }

        .data th {
            font-size: 8px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #71717a;
            border-bottom: 1px solid #18181b;
            padding: 0 6px 5px;
            text-align: left;
        }
        .data td { padding: 5px 6px; border-bottom: 1px solid #e4e4e7; }
        .data tr.total td {
            border-top: 1.2px solid #18181b;
            border-bottom: none;
            font-weight: bold;
        }

        .note {
            margin-top: 20px;
            padding: 8px 10px;
            border: 1px solid #e4e4e7;
            border-radius: 4px;
            background: #fffbeb;
            font-size: 9px;
            color: #713f12;
        }

        .footer { margin-top: 24px; font-size: 8px; color: #71717a; }
        .negative { color: #be123c; }
    </style>
</head>
<body>

<table>
    <tr>
        <td>
            <div class="business-name">{{ $business['business_name'] ?: config('app.name') }}</div>
            @if ($business['business_reg_no'])
                <div class="muted">{{ __('pdf.reg_no') }}: {{ $business['business_reg_no'] }}</div>
            @endif
        </td>
        <td class="right">
            <p class="title">{{ $title }}</p>
            <div class="muted">{{ $subtitle }}</div>
        </td>
    </tr>
</table>

@if (! empty($figures))
    <table class="figures">
        <tr>
            @foreach ($figures as $figure)
                <td>
                    <div class="label">{{ $figure['label'] }}</div>
                    <div class="figure-value {{ ($figure['negative'] ?? false) ? 'negative' : '' }}">
                        {{ $figure['value'] }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
@endif

@foreach ($tables as $table)
    <div class="section">
        <h2>{{ $table['heading'] }}</h2>

        @if (empty($table['rows']))
            <p class="muted">{{ __('report.no_rows') }}</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($table['columns'] as $index => $column)
                            <th class="{{ $index === 0 ? '' : 'right' }}">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table['rows'] as $row)
                        <tr class="{{ ($row['total'] ?? false) ? 'total' : '' }}">
                            @foreach ($row['cells'] as $index => $cell)
                                <td class="{{ $index === 0 ? '' : 'right nowrap' }}">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach

@if ($note !== null)
    <div class="note">{{ $note }}</div>
@endif

<div class="footer">
    {{ __('report.generated_on', ['date' => $date(now()->toDateString())]) }}
</div>

</body>
</html>
