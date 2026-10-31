<?php

use App\Actions\Invoices\StoreInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Http\Controllers\PublicInvoiceController;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);
    Setting::putMany([
        'business_name' => 'KiraBisnes Sdn Bhd',
        'bank_details' => "Maybank 1234567890\nKiraBisnes Sdn Bhd",
    ]);

    $this->client = Client::factory()->speaking(Locale::English)->create([
        'name' => 'Lee Wei',
        'company' => 'Wei Trading Sdn Bhd',
        'email' => 'lee@wei.test',
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** The link the switcher offers, signed by the server. */
function signedLink(Invoice $invoice, string $locale): string
{
    return URL::signedRoute('public.invoice', [
        'invoice' => $invoice->id,
        'lang' => $locale,
    ]);
}

function publicInvoice(InvoiceStatus $status = InvoiceStatus::Sent, string $locale = 'en'): Invoice
{
    $invoice = app(StoreInvoice::class)->handle([
        'client_id' => test()->client->id,
        'issue_date' => '2026-10-01',
        'locale' => $locale,
        'items' => [['description' => 'Inventory system', 'quantity' => 1, 'unit_price' => 8_000_00]],
    ]);

    $invoice->update(['status' => $status, 'sent_at' => now()]);

    return $invoice->refresh();
}

it('opens for a client holding the signed link, with no account', function () {
    $invoice = publicInvoice();

    $this->get(PublicInvoiceController::linkFor($invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Invoice')
            ->where('invoice.number', $invoice->number)
            ->where('invoice.total', 8_000_00)
            ->where('client.display_name', 'Wei Trading Sdn Bhd')
            ->where('business.name', 'KiraBisnes Sdn Bhd')
            ->has('invoice.items', 1),
        );
});

it('refuses a link with no signature', function () {
    $invoice = publicInvoice();

    // Holding the link is the authorisation, so an unsigned URL is nothing.
    $this->get('/i/'.$invoice->id)->assertForbidden();
});

it('refuses a signature edited to point at another invoice', function () {
    $mine = publicInvoice();
    $other = publicInvoice();

    $link = PublicInvoiceController::linkFor($mine);
    $tampered = str_replace('/i/'.$mine->id.'?', '/i/'.$other->id.'?', $link);

    $this->get($tampered)->assertForbidden();
});

it('never shows a draft', function () {
    // A link to one would be a bill the owner had not finished writing.
    $invoice = publicInvoice(InvoiceStatus::Draft);

    $this->get(PublicInvoiceController::linkFor($invoice))->assertNotFound();
});

it('shows a voided invoice, so a client who kept the link is not confused', function () {
    $invoice = publicInvoice(InvoiceStatus::Void);

    $this->get(PublicInvoiceController::linkFor($invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('invoice.status', 'void'));
});

it('reads in the language the invoice was written in', function () {
    $english = publicInvoice(locale: 'en');
    $malay = publicInvoice(locale: 'ms');

    $this->get(PublicInvoiceController::linkFor($english))
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));

    $this->get(PublicInvoiceController::linkFor($malay))
        ->assertInertia(fn ($page) => $page->where('locale', 'ms'));
});

it('lets the visitor ask for the other language', function () {
    $invoice = publicInvoice(locale: 'en');

    $this->get(signedLink($invoice, 'ms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'ms'));
});

it('ignores a language it does not have', function () {
    $invoice = publicInvoice(locale: 'en');

    $this->get(signedLink($invoice, 'fr'))
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('refuses a language tacked onto a signed link by hand', function () {
    // The signature covers the query string, so a parameter added after the
    // fact invalidates it. That is the point: the switcher uses links the
    // server signed, not string concatenation.
    $invoice = publicInvoice(locale: 'en');

    $this->get(PublicInvoiceController::linkFor($invoice).'&lang=ms')
        ->assertForbidden();
});

it('offers both language links, each still signed', function () {
    $invoice = publicInvoice();
    $links = [];

    $this->get(PublicInvoiceController::linkFor($invoice))
        ->assertInertia(function ($page) use (&$links) {
            $links = $page->toArray()['props']['links'];
        });

    // A switcher that dropped the signature would lock the client out.
    $this->get($links['ms'])->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'ms'));
    $this->get($links['en'])->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('serves the PDF from a signed link too', function () {
    $invoice = publicInvoice();

    $response = $this->get(route('public.invoice.pdf', $invoice));
    $response->assertForbidden();

    $signed = URL::signedRoute(
        'public.invoice.pdf',
        ['invoice' => $invoice->id],
    );

    $this->get($signed)->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('shows how to pay while money is still owed', function () {
    $invoice = publicInvoice();

    $this->get(PublicInvoiceController::linkFor($invoice))
        ->assertInertia(fn ($page) => $page
            ->where('invoice.outstanding', 8_000_00)
            ->where('business.bank_details', "Maybank 1234567890\nKiraBisnes Sdn Bhd"),
        );
});

it('leaves the owner language alone', function () {
    $owner = User::factory()->create(['locale' => Locale::Malay]);
    $invoice = publicInvoice(locale: 'en');

    $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    $this->get(PublicInvoiceController::linkFor($invoice))->assertOk();

    // The owner's own pages still read in Malay afterwards.
    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('locale', 'ms'));
});
