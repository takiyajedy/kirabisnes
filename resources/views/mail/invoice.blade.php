{{--
    Written in the client's language: the Mailable sets the locale before this
    renders, so every __() here follows the invoice, not the app.
--}}
<x-mail::message>
# {{ __('mail.invoice.greeting', ['name' => $client->displayName()]) }}

{{ __('mail.invoice.intro', [
    'business' => $business['business_name'] ?: config('app.name'),
    'number' => $invoice->number,
]) }}

<x-mail::table>
| | |
|:---|---:|
| **{{ __('invoice.number') }}** | {{ $invoice->number }} |
| **{{ __('document.total') }}** | {{ $total }} |
@if ($invoice->paid_amount > 0)
| **{{ __('invoice.outstanding') }}** | {{ $outstanding }} |
@endif
| **{{ __('invoice.due_date') }}** | {{ $dueDate }} |
</x-mail::table>

{{ __('mail.invoice.attached') }}

<x-mail::button :url="$link">
{{ __('mail.invoice.view_online') }}
</x-mail::button>

@if ($business['bank_details'])
**{{ __('pdf.payment_details') }}**

{{ $business['bank_details'] }}
@endif

{{ __('mail.invoice.thanks') }}

{{ $business['business_name'] ?: config('app.name') }}
</x-mail::message>
