<?php

namespace App\Mail;

use App\Http\Controllers\PublicInvoiceController;
use App\Models\Invoice;
use App\Services\DocumentPdfService;
use App\Support\BusinessProfile;
use App\Support\DocumentDate;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The e-mail that carries an invoice to a client.
 *
 * It is written in the client's language, which the controller sets with
 * `->locale()`; the attached PDF follows the invoice's own locale, and the
 * two are the same thing (PLAN.md phase 3).
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        $business = BusinessProfile::all();
        $name = $business['business_name'] ?: config('app.name');

        return new Envelope(
            subject: __('mail.invoice.subject', [
                'number' => $this->invoice->number,
                'business' => $name,
            ]),
            replyTo: array_filter([$business['business_email'] ?: null]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'client' => $this->invoice->client,
                'business' => BusinessProfile::all(),
                'total' => Money::format(
                    $this->invoice->total,
                    $this->invoice->locale->value,
                ),
                'outstanding' => Money::format(
                    $this->invoice->outstanding(),
                    $this->invoice->locale->value,
                ),
                // A signed link, so the client can open the invoice
                // without an account (PLAN.md phase 6).
                'link' => PublicInvoiceController::linkFor($this->invoice),
                'dueDate' => DocumentDate::long(
                    $this->invoice->due_date,
                    $this->invoice->locale->value,
                ),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdfs = app(DocumentPdfService::class);

        return [
            Attachment::fromData(
                fn (): string => $pdfs->forInvoice($this->invoice)->output(),
                $pdfs->fileName($this->invoice, $this->invoice->client),
            )->withMime('application/pdf'),
        ];
    }
}
