{{--
    One template for an invoice and for a quotation: the two documents differ
    in their labels and a couple of rows, not in their shape.

    Always light, whatever the app's theme (PLAN.md 3.6), and written in the
    document's own language, which the service sets before rendering. The CSS
    is hand written and inline: dompdf lays out with tables, not flexbox, so
    the app's Tailwind build has nothing to offer here.
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document->number }}</title>
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

        .title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: -0.4px;
            margin: 0 0 2px;
        }
        .business-name { font-size: 13px; font-weight: bold; }

        .label {
            font-size: 8px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #71717a;
            padding-bottom: 3px;
        }

        .panel {
            background: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 6px;
            padding: 10px 12px;
        }

        .items { margin-top: 22px; }
        .items th {
            font-size: 8px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #71717a;
            border-bottom: 1px solid #18181b;
            padding: 0 6px 6px;
            text-align: left;
        }
        .items td {
            padding: 8px 6px;
            border-bottom: 1px solid #e4e4e7;
        }
        .items .col-no { width: 24px; }
        .items .col-qty { width: 54px; }
        .items .col-price { width: 96px; }
        .items .col-amount { width: 104px; }

        .totals { margin-top: 14px; }
        .totals td { padding: 3px 6px; }
        .totals .grand td {
            border-top: 1.5px solid #18181b;
            padding-top: 7px;
            font-size: 13px;
            font-weight: bold;
        }

        .footer { margin-top: 26px; }
        .footer-note {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #e4e4e7;
            font-size: 9px;
            color: #71717a;
        }

        .stamp {
            display: inline-block;
            border: 1.5px solid #71717a;
            border-radius: 4px;
            padding: 3px 9px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #71717a;
        }
        .stamp-paid { border-color: #047857; color: #047857; }
        .stamp-void { border-color: #be123c; color: #be123c; }
    </style>
</head>
<body>

{{-- Letterhead --}}
<table>
    <tr>
        <td style="width: 58%">
            @if ($logo !== null)
                <img src="{{ $logo }}" alt="" style="max-height: 46px; margin-bottom: 6px">
            @endif
            <div class="business-name">{{ $business['business_name'] ?: config('app.name') }}</div>
            @if ($business['business_reg_no'])
                <div class="muted">{{ __('pdf.reg_no') }}: {{ $business['business_reg_no'] }}</div>
            @endif
            @if ($business['business_address'])
                <div class="muted">{!! nl2br(e($business['business_address'])) !!}</div>
            @endif
            @if ($business['business_phone'])
                <div class="muted">{{ $business['business_phone'] }}</div>
            @endif
            @if ($business['business_email'])
                <div class="muted">{{ $business['business_email'] }}</div>
            @endif
        </td>
        <td class="right">
            <p class="title">{{ $heading }}</p>
            <div class="strong">{{ $document->number }}</div>
            @if ($stamp !== null)
                <div style="margin-top: 8px">
                    <span class="stamp stamp-{{ $stamp }}">{{ __('invoice.status.'.$stamp) }}</span>
                </div>
            @endif
        </td>
    </tr>
</table>

{{-- Who it is for, and the dates --}}
<table style="margin-top: 24px">
    <tr>
        <td style="width: 54%; padding-right: 16px">
            <div class="label">{{ __('pdf.billed_to') }}</div>
            <div class="panel">
                <div class="strong">{{ $client->displayName() }}</div>
                @if ($client->company && $client->name !== $client->company)
                    <div>{{ $client->name }}</div>
                @endif
                @if ($client->reg_no)
                    <div class="muted">{{ __('pdf.reg_no') }}: {{ $client->reg_no }}</div>
                @endif
                @if ($client->address)
                    <div class="muted">{!! nl2br(e($client->address)) !!}</div>
                @endif
                @if ($client->email)
                    <div class="muted">{{ $client->email }}</div>
                @endif
                @if ($client->phone)
                    <div class="muted">{{ $client->phone }}</div>
                @endif
            </div>
        </td>
        <td>
            <table>
                @foreach ($meta as $row)
                    <tr>
                        <td class="muted" style="padding: 2px 0">{{ $row['label'] }}</td>
                        <td class="right strong nowrap" style="padding: 2px 0">{{ $row['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

{{-- The work --}}
<table class="items">
    <thead>
        <tr>
            <th class="col-no">#</th>
            <th>{{ __('document.item.description') }}</th>
            <th class="col-qty right">{{ __('document.item.quantity') }}</th>
            <th class="col-price right">{{ __('document.item.unit_price') }}</th>
            <th class="col-amount right">{{ __('document.item.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($document->items as $index => $item)
            <tr>
                <td class="muted">{{ $index + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="right nowrap">{{ $quantity($item->quantity) }}</td>
                <td class="right nowrap">{{ $money($item->unit_price) }}</td>
                <td class="right nowrap">{{ $money($item->amount) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Totals --}}
<table class="totals">
    <tr>
        <td style="width: 58%"></td>
        <td>
            <table>
                <tr>
                    <td class="muted">{{ __('document.subtotal') }}</td>
                    <td class="right nowrap">{{ $money($document->subtotal) }}</td>
                </tr>
                @if ($document->discount > 0)
                    <tr>
                        <td class="muted">{{ __('document.discount') }}</td>
                        <td class="right nowrap">-{{ $money($document->discount) }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td>{{ __('document.total') }}</td>
                    <td class="right nowrap">{{ $money($document->total) }}</td>
                </tr>
                @foreach ($extraTotals as $row)
                    <tr>
                        <td class="{{ $row['strong'] ?? false ? 'strong' : 'muted' }}">{{ $row['label'] }}</td>
                        <td class="right nowrap {{ $row['strong'] ?? false ? 'strong' : '' }}">{{ $row['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

{{-- Notes, terms, how to pay --}}
<div class="footer">
    <table>
        <tr>
            <td style="width: 54%; padding-right: 16px">
                @if ($document->notes)
                    <div class="label">{{ __('document.notes') }}</div>
                    <div>{!! nl2br(e($document->notes)) !!}</div>
                @endif
                @if ($document->terms)
                    <div class="label" style="margin-top: 12px">{{ __('document.terms') }}</div>
                    <div>{!! nl2br(e($document->terms)) !!}</div>
                @endif
            </td>
            <td>
                @if ($showBankDetails && $business['bank_details'])
                    <div class="label">{{ __('pdf.payment_details') }}</div>
                    <div class="panel">{!! nl2br(e($business['bank_details'])) !!}</div>
                @endif
            </td>
        </tr>
    </table>

    @if ($footerNote)
        <div class="footer-note">{!! nl2br(e($footerNote)) !!}</div>
    @endif
</div>

</body>
</html>
