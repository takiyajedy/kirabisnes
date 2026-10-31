<?php

use App\Actions\Invoices\StoreInvoice;
use App\Actions\Quotations\StoreQuotation;
use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentPdfService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    Setting::putMany([
        'business_name' => 'KiraBisnes Sdn Bhd',
        'business_email' => 'hello@kirabisnes.test',
        'bank_details' => "Maybank 1234567890\nKiraBisnes Sdn Bhd",
    ]);

    // The owner reads Malay; the documents must not follow that.
    $this->actingAs(User::factory()->create(['locale' => Locale::Malay]));
    app()->setLocale('ms');

    $this->pdfs = app(DocumentPdfService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function invoiceFor(Locale $locale, int $sen = 2_500_00, array $overrides = []): Invoice
{
    $client = Client::factory()->speaking($locale)->create([
        'name' => 'Lee Wei',
        'company' => 'Wei Trading Sdn Bhd',
        'email' => 'lee@wei.test',
    ]);

    return app(StoreInvoice::class)->handle([
        'client_id' => $client->id,
        'issue_date' => '2026-10-01',
        'locale' => $locale->value,
        'notes' => 'Terima kasih',
        'items' => [
            ['description' => 'Reka bentuk laman web', 'quantity' => 1, 'unit_price' => $sen],
        ],
        ...$overrides,
    ]);
}

/**
 * The text layer of a generated PDF, normalised for comparison.
 *
 * Two things make a raw comparison fail: the labels carry
 * `text-transform: uppercase`, which dompdf bakes into the text, and ICU
 * puts a non-breaking space between "RM" and the number.
 */
function pdfText(string $binary): string
{
    $text = (new Parser)->parseContent($binary)->getText();

    return mb_strtolower((string) preg_replace('/\s+/u', ' ', $text));
}

it('renders an invoice as a PDF', function () {
    $invoice = invoiceFor(Locale::Malay);

    $output = $this->pdfs->forInvoice($invoice)->output();

    expect($output)->toStartWith('%PDF-')
        ->and(strlen($output))->toBeGreaterThan(1000);
});

it('writes a Malay invoice in Malay while the app is in Malay', function () {
    $invoice = invoiceFor(Locale::Malay);

    $text = pdfText($this->pdfs->forInvoice($invoice)->output());

    expect($text)->toContain('invois')
        ->toContain('dibilkan kepada')
        ->toContain('jumlah')
        ->toContain('tarikh jatuh tempoh')
        ->toContain('inv-2026-0001')
        ->toContain('wei trading sdn bhd');
});

it('writes an English invoice in English even though the app is in Malay', function () {
    // The acceptance test for phase 3: the client's language wins.
    $invoice = invoiceFor(Locale::English);

    $text = pdfText($this->pdfs->forInvoice($invoice)->output());

    expect($text)->toContain('invoice')
        ->toContain('billed to')
        ->toContain('due date')
        ->toContain('payment details')
        // And none of the Malay labels leaked in.
        ->not->toContain('dibilkan kepada')
        ->not->toContain('tarikh jatuh tempoh');
});

it('leaves the app language alone after rendering', function () {
    $invoice = invoiceFor(Locale::English);

    $this->pdfs->forInvoice($invoice)->output();

    expect(app()->getLocale())->toBe('ms');
});

it('formats the dates in the document language', function () {
    $malay = pdfText($this->pdfs->forInvoice(invoiceFor(Locale::Malay))->output());
    $english = pdfText($this->pdfs->forInvoice(invoiceFor(Locale::English))->output());

    expect($malay)->toContain('1 okt 2026')
        ->and($english)->toContain('1 oct 2026');
});

it('abbreviates the month the way the pages do', function () {
    // A PDF made from a page must not spell the date differently to it.
    $invoice = invoiceFor(Locale::Malay, 100_00, ['issue_date' => '2026-08-04']);

    expect(pdfText($this->pdfs->forInvoice($invoice)->output()))
        ->toContain('4 ogo 2026')
        ->not->toContain('4 ogs 2026');
});

it('shows the amounts and the total', function () {
    $invoice = invoiceFor(Locale::English, 2_500_00);

    $text = pdfText($this->pdfs->forInvoice($invoice)->output());

    expect($text)->toContain('rm 2,500.00');
});

it('shows what is paid and what is still owed once there is a payment', function () {
    $invoice = invoiceFor(Locale::English, 1_000_00);
    $invoice->update(['paid_amount' => 400_00, 'status' => InvoiceStatus::Partial]);

    $text = pdfText($this->pdfs->forInvoice($invoice)->output());

    expect($text)->toContain('paid')
        ->toContain('outstanding')
        ->toContain('rm 600.00');
});

it('stamps a voided or overdue invoice so the reader cannot miss it', function () {
    // "Void" and "Overdue" appear nowhere else on the page, unlike "Paid",
    // which is also a totals label — so these prove the stamp is drawn.
    $voided = invoiceFor(Locale::English, 500_00);
    $voided->update(['status' => InvoiceStatus::Void]);

    $late = invoiceFor(Locale::English, 500_00);
    $late->update(['status' => InvoiceStatus::Overdue]);

    $clean = invoiceFor(Locale::English, 500_00);

    expect(pdfText($this->pdfs->forInvoice($voided)->output()))->toContain('void')
        ->and(pdfText($this->pdfs->forInvoice($late)->output()))->toContain('overdue')
        ->and(pdfText($this->pdfs->forInvoice($clean)->output()))
        ->not->toContain('void')
        ->not->toContain('overdue');
});

it('renders a quotation without the bank details', function () {
    $client = Client::factory()->speaking(Locale::English)->create();

    $quotation = app(StoreQuotation::class)->handle([
        'client_id' => $client->id,
        'issue_date' => '2026-10-01',
        'valid_until' => '2026-10-31',
        'locale' => 'en',
        'items' => [['description' => 'Scoping', 'quantity' => 1, 'unit_price' => 800_00]],
    ]);

    $text = pdfText($this->pdfs->forQuotation($quotation)->output());

    expect($text)->toContain('quotation')
        ->toContain('valid until')
        // A quotation is not a request for money yet.
        ->not->toContain('payment details');
});

it('names the file after the document and the client', function () {
    $invoice = invoiceFor(Locale::Malay);

    expect($this->pdfs->fileName($invoice, $invoice->client))
        ->toBe('INV-2026-0001-Wei-Trading-Sdn-Bhd.pdf');
});

it('serves the invoice PDF from a signed-in route', function () {
    $invoice = invoiceFor(Locale::Malay);

    $response = $this->get(route('invoices.pdf', $invoice));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('offers the PDF as a download when asked', function () {
    $invoice = invoiceFor(Locale::Malay);

    $this->get(route('invoices.pdf', [$invoice, 'download' => 1]))
        ->assertOk()
        ->assertDownload('INV-2026-0001-Wei-Trading-Sdn-Bhd.pdf');
});

it('keeps guests away from the PDF', function () {
    $invoice = invoiceFor(Locale::Malay);
    auth()->logout();

    $this->get(route('invoices.pdf', $invoice))->assertRedirect(route('login'));
});

it('puts the business logo on the document when there is one', function () {
    // dompdf reads the image off disk, so the stored path has to resolve.
    Storage::fake('public');
    $logo = UploadedFile::fake()->image('logo.png', 120, 40);
    $path = $logo->store('logos', 'public');
    Setting::put('logo_path', $path);

    $invoice = invoiceFor(Locale::Malay);

    // It renders without complaining about a missing file, which is what a
    // bad path would do.
    expect($this->pdfs->forInvoice($invoice)->output())->toStartWith('%PDF-');
});

it('renders without a logo when none is set', function () {
    Setting::put('logo_path', null);

    expect($this->pdfs->forInvoice(invoiceFor(Locale::Malay))->output())
        ->toStartWith('%PDF-');
});

it('ignores a logo path that no longer points at a file', function () {
    Setting::put('logo_path', 'logos/deleted-by-hand.png');

    expect($this->pdfs->forInvoice(invoiceFor(Locale::Malay))->output())
        ->toStartWith('%PDF-');
});
