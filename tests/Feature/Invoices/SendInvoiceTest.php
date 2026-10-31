<?php

use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    Setting::put('business_name', 'KiraBisnes Sdn Bhd');

    // The owner reads Malay.
    $this->actingAs(User::factory()->create(['locale' => Locale::Malay]));
    app()->setLocale('ms');

    Mail::fake();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function invoiceToSend(Locale $locale, array $overrides = []): Invoice
{
    $client = Client::factory()->speaking($locale)->create([
        'email' => 'client@example.test',
        'name' => 'Lee Wei',
    ]);

    return Invoice::factory()
        ->withStatus(InvoiceStatus::Draft)
        ->totalling(1_200_00)
        ->create(['client_id' => $client->id, 'locale' => $locale, ...$overrides]);
}

it('e-mails the invoice to the client and marks it sent', function () {
    $invoice = invoiceToSend(Locale::Malay);

    $this->from(route('invoices.show', $invoice))
        ->post(route('invoices.send', $invoice))
        ->assertInertiaFlash('toast.type', 'success');

    Mail::assertSent(
        InvoiceMail::class,
        fn (InvoiceMail $mail) => $mail->hasTo('client@example.test')
            && $mail->invoice->is($invoice),
    );

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->sent_at)->not->toBeNull();
});

it('sends to a client who reads English in English, while the app is in Malay', function () {
    $invoice = invoiceToSend(Locale::English);

    $this->post(route('invoices.send', $invoice));

    // The acceptance test for phase 3: the mail follows the client.
    Mail::assertSent(
        InvoiceMail::class,
        fn (InvoiceMail $mail) => $mail->locale === 'en',
    );
});

it('sends to a Malay client in Malay', function () {
    $invoice = invoiceToSend(Locale::Malay);

    $this->post(route('invoices.send', $invoice));

    Mail::assertSent(
        InvoiceMail::class,
        fn (InvoiceMail $mail) => $mail->locale === 'ms',
    );
});

it('writes the body in the client language while the app is in Malay', function () {
    $invoice = invoiceToSend(Locale::English);

    // render() applies the mailable's own locale, which is what the
    // controller sets from the invoice.
    $html = (new InvoiceMail($invoice))->locale('en')->render();

    expect($html)->toContain('has issued invoice')
        ->toContain('Due date')
        ->not->toContain('telah mengeluarkan')
        ->not->toContain('Tarikh jatuh tempoh')
        // And the owner's own language is back afterwards.
        ->and(app()->getLocale())->toBe('ms');
});

it('writes a Malay body for a Malay client', function () {
    $invoice = invoiceToSend(Locale::Malay);

    $html = (new InvoiceMail($invoice))->locale('ms')->render();

    expect($html)->toContain('telah mengeluarkan')
        ->not->toContain('has issued invoice');
});

it('writes the subject in the document language', function () {
    $invoice = invoiceToSend(Locale::English);

    app()->setLocale('en');
    $subject = (new InvoiceMail($invoice))->envelope()->subject;
    app()->setLocale('ms');

    expect($subject)->toContain('Invoice '.$invoice->number)
        ->toContain('KiraBisnes Sdn Bhd');
});

it('attaches the invoice as a PDF named after the document', function () {
    $invoice = invoiceToSend(Locale::English);

    $this->post(route('invoices.send', $invoice));

    Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
        $attachments = $mail->attachments();

        return count($attachments) === 1
            && str_starts_with($attachments[0]->as ?? '', $invoice->number)
            && str_ends_with($attachments[0]->as ?? '', '.pdf');
    });
});

it('will not send to a client with no e-mail address', function () {
    $client = Client::factory()->create(['email' => null]);
    $invoice = Invoice::factory()->withStatus(InvoiceStatus::Draft)
        ->create(['client_id' => $client->id]);

    $this->from(route('invoices.show', $invoice))
        ->post(route('invoices.send', $invoice))
        ->assertInertiaFlash('toast.type', 'error');

    Mail::assertNothingSent();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Draft);
});

it('will not send a voided invoice', function () {
    $invoice = invoiceToSend(Locale::Malay);
    $invoice->update(['status' => InvoiceStatus::Void]);

    $this->post(route('invoices.send', $invoice))->assertForbidden();

    Mail::assertNothingSent();
});

it('does not unpay an invoice by sending it again', function () {
    $invoice = invoiceToSend(Locale::Malay, [
        'status' => InvoiceStatus::Partial,
        'paid_amount' => 300_00,
        'sent_at' => CarbonImmutable::parse('2026-10-02 10:00:00'),
    ]);

    $this->post(route('invoices.send', $invoice));

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Partial)
        // The first send is the one on record.
        ->and($invoice->sent_at?->toDateString())->toBe('2026-10-02');
});

it('marks an invoice sent without e-mailing it', function () {
    $invoice = invoiceToSend(Locale::Malay);

    $this->from(route('invoices.show', $invoice))
        ->post(route('invoices.mark-sent', $invoice))
        ->assertInertiaFlash('toast.type', 'success');

    Mail::assertNothingSent();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Sent);
});
