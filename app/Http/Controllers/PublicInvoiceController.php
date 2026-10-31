<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\DocumentPdfService;
use App\Support\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page a client sees when they follow the link in their invoice e-mail
 * (PLAN.md phase 6).
 *
 * No login: the URL is signed, so holding the link is the authorisation and
 * it cannot be edited into someone else's invoice. A draft is never shown —
 * a link to one would be a bill the owner had not finished writing.
 *
 * The page is rendered in the invoice's own language, not the owner's and
 * not the visitor's browser setting, because that is the language the
 * document was written in. A switcher is offered anyway: a client who would
 * rather read the other one can say so.
 */
class PublicInvoiceController extends Controller
{
    /**
     * A link that can be put in an e-mail. It does not expire: an invoice
     * can be paid late, and a dead link would just mean a phone call.
     */
    public static function linkFor(Invoice $invoice): string
    {
        return URL::signedRoute('public.invoice', ['invoice' => $invoice->id]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        $invoice->load(['client', 'items', 'project']);

        // The document's own language wins, unless the visitor asks for the
        // other one with ?lang=.
        $locale = $this->localeFor($request, $invoice);
        App::setLocale($locale);

        $business = BusinessProfile::all();

        return Inertia::render('Public/Invoice', [
            'invoice' => [
                'number' => $invoice->number,
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'status_tone' => $invoice->status->tone(),
                'is_late' => $invoice->isLate(),
                'subtotal' => $invoice->subtotal,
                'discount' => $invoice->discount,
                'total' => $invoice->total,
                'paid_amount' => $invoice->paid_amount,
                'outstanding' => $invoice->outstanding(),
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'project_name' => $invoice->project?->name,
                'items' => $invoice->items->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                    'sort_order' => $item->sort_order,
                ])->all(),
            ],
            'client' => [
                'display_name' => $invoice->client->displayName(),
                'name' => $invoice->client->name,
                'address' => $invoice->client->address,
                'reg_no' => $invoice->client->reg_no,
            ],
            'business' => [
                'name' => $business['business_name'] ?: config('app.name'),
                'reg_no' => $business['business_reg_no'],
                'email' => $business['business_email'],
                'phone' => $business['business_phone'],
                'address' => $business['business_address'],
                'bank_details' => $business['bank_details'],
            ],
            // Both links carry the signature, so the switcher and the
            // download still open.
            'links' => [
                'ms' => $this->linkWithLanguage($invoice, 'ms'),
                'en' => $this->linkWithLanguage($invoice, 'en'),
                'pdf' => URL::signedRoute('public.invoice.pdf', ['invoice' => $invoice->id]),
            ],
            'locale' => $locale,
        ]);
    }

    /**
     * The same invoice as a PDF, for a client who wants to keep a copy.
     */
    public function pdf(Invoice $invoice, DocumentPdfService $pdfs): HttpResponse
    {
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        $invoice->load(['client', 'items', 'project']);

        return $pdfs->forInvoice($invoice)
            ->download($pdfs->fileName($invoice, $invoice->client));
    }

    private function localeFor(Request $request, Invoice $invoice): string
    {
        $asked = $request->string('lang')->value();

        return in_array($asked, ['ms', 'en'], true)
            ? $asked
            : $invoice->locale->value;
    }

    private function linkWithLanguage(Invoice $invoice, string $locale): string
    {
        return URL::signedRoute('public.invoice', [
            'invoice' => $invoice->id,
            'lang' => $locale,
        ]);
    }
}
