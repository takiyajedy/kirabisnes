<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Support\BusinessProfile;
use App\Support\DocumentDate;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an invoice or a quotation as a PDF (PLAN.md phase 3).
 *
 * The document is written in its own language, not the owner's: a client who
 * reads English gets an English invoice even while the app is in Malay. That
 * is done by swapping the app locale around the render, so every `__()` in
 * the template and every date and amount formatter follows the document.
 *
 * The PDF is always light: it is made to be printed and e-mailed.
 */
class DocumentPdfService
{
    public function forInvoice(Invoice $invoice): PdfWrapper
    {
        $invoice->loadMissing(['client', 'items', 'project']);

        return $this->render($invoice->locale->value, function () use ($invoice): array {
            $locale = $invoice->locale->value;

            $meta = [
                [
                    'label' => __('invoice.issue_date'),
                    'value' => DocumentDate::long($invoice->issue_date, $locale),
                ],
                [
                    'label' => __('invoice.due_date'),
                    'value' => DocumentDate::long($invoice->due_date, $locale),
                ],
            ];

            if ($invoice->project !== null) {
                $meta[] = ['label' => __('project.one'), 'value' => $invoice->project->name];
            }

            return [
                'document' => $invoice,
                'client' => $invoice->client,
                'heading' => __('invoice.one'),
                'stamp' => $this->stampFor($invoice),
                'showBankDetails' => true,
                'meta' => $meta,
                'extraTotals' => $invoice->paid_amount > 0 ? [
                    [
                        'label' => __('invoice.paid_amount'),
                        'value' => '-'.Money::format($invoice->paid_amount),
                    ],
                    [
                        'label' => __('invoice.outstanding'),
                        'value' => Money::format($invoice->outstanding()),
                        'strong' => true,
                    ],
                ] : [],
            ];
        });
    }

    public function forQuotation(Quotation $quotation): PdfWrapper
    {
        $quotation->loadMissing(['client', 'items', 'project']);

        return $this->render($quotation->locale->value, function () use ($quotation): array {
            $locale = $quotation->locale->value;

            $meta = [
                [
                    'label' => __('quotation.issue_date'),
                    'value' => DocumentDate::long($quotation->issue_date, $locale),
                ],
                [
                    'label' => __('quotation.valid_until'),
                    'value' => DocumentDate::long($quotation->valid_until, $locale),
                ],
            ];

            if ($quotation->project !== null) {
                $meta[] = ['label' => __('project.one'), 'value' => $quotation->project->name];
            }

            return [
                'document' => $quotation,
                'client' => $quotation->client,
                'heading' => __('quotation.one'),
                'stamp' => null,
                // A quotation is not a request for money yet.
                'showBankDetails' => false,
                'meta' => $meta,
                'extraTotals' => [],
            ];
        });
    }

    /**
     * A file name a client can recognise in their downloads folder.
     */
    public function fileName(Invoice|Quotation $document, Client $client): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $client->displayName()) ?? '';

        return trim($document->number.'-'.trim($slug, '-'), '-').'.pdf';
    }

    /**
     * @param  callable(): array<string, mixed>  $data  Built inside the
     *                                                  document's locale, so
     *                                                  its labels follow it.
     */
    private function render(string $locale, callable $data): PdfWrapper
    {
        $previous = App::getLocale();

        App::setLocale($locale);

        try {
            $business = BusinessProfile::all();

            /** @var array<string, string> $footerNotes */
            $footerNotes = is_array($business['invoice_footer_note'])
                ? $business['invoice_footer_note']
                : [];

            return Pdf::loadView('pdf.document', [
                ...$data(),
                'locale' => $locale,
                'business' => $business,
                'logo' => $this->logo($business['logo_path']),
                'footerNote' => $footerNotes[$locale] ?? null,
                'money' => fn (int $sen): string => Money::format($sen, $locale),
                'quantity' => fn (string|float $value): string => rtrim(
                    rtrim(number_format((float) $value, 2, '.', ''), '0'),
                    '.',
                ),
            ])->setPaper('a4');
        } finally {
            // Whatever happens, the owner's own language comes back.
            App::setLocale($previous);
        }
    }

    /**
     * dompdf reads images off the filesystem, so the logo is passed as a
     * path rather than a URL it would have to fetch.
     */
    private function logo(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $full = Storage::disk('public')->path($path);

        return is_file($full) ? $full : null;
    }

    /**
     * The word stamped across the top, for a document whose state the reader
     * needs to see at a glance.
     */
    private function stampFor(Invoice $invoice): ?string
    {
        return match ($invoice->status) {
            InvoiceStatus::Paid => 'paid',
            InvoiceStatus::Void => 'void',
            InvoiceStatus::Overdue => 'overdue',
            default => null,
        };
    }
}
