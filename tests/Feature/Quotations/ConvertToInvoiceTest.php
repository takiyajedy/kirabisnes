<?php

use App\Actions\Invoices\UpdateInvoice;
use App\Actions\Quotations\ConvertQuotationToInvoice;
use App\Actions\Quotations\StoreQuotation;
use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->client = Client::factory()->speaking(Locale::English)->create();
    $this->convert = app(ConvertQuotationToInvoice::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function quotationFor(Client $client, array $overrides = []): Quotation
{
    return app(StoreQuotation::class)->handle([
        'client_id' => $client->id,
        'issue_date' => '2026-10-01',
        'valid_until' => '2026-10-31',
        'locale' => $client->preferred_locale->value,
        'status' => QuotationStatus::Sent->value,
        'notes' => 'Terima kasih',
        'terms' => 'Bayaran 50% deposit',
        'items' => [
            ['description' => 'Reka bentuk', 'quantity' => 1, 'unit_price' => 1_500_00],
            ['description' => 'Pembangunan', 'quantity' => '2.5', 'unit_price' => 1_200_00],
        ],
        ...$overrides,
    ]);
}

it('copies the lines, discount and language onto the invoice', function () {
    $quotation = quotationFor($this->client, ['discount' => 500_00]);

    $invoice = $this->convert->handle($quotation);

    expect($invoice->items)->toHaveCount(2)
        ->and($invoice->items[0]->description)->toBe('Reka bentuk')
        ->and($invoice->items[1]->amount)->toBe(3_000_00)
        ->and($invoice->subtotal)->toBe(4_500_00)
        ->and($invoice->discount)->toBe(500_00)
        ->and($invoice->total)->toBe(4_000_00)
        // The client reads English, so the invoice is written in English too.
        ->and($invoice->locale)->toBe(Locale::English)
        ->and($invoice->notes)->toBe('Terima kasih')
        ->and($invoice->terms)->toBe('Bayaran 50% deposit');
});

it('links the two documents both ways and accepts the quotation', function () {
    $quotation = quotationFor($this->client);

    $invoice = $this->convert->handle($quotation);
    $quotation->refresh();

    expect($quotation->status)->toBe(QuotationStatus::Accepted)
        ->and($quotation->converted_invoice_id)->toBe($invoice->id)
        ->and($invoice->quotation_id)->toBe($quotation->id);
});

it('carries the project over', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);
    $quotation = quotationFor($this->client, ['project_id' => $project->id]);

    expect($this->convert->handle($quotation)->project_id)->toBe($project->id);
});

it('gives the invoice its own number and a fresh issue date', function () {
    $quotation = quotationFor($this->client);

    $invoice = $this->convert->handle($quotation);

    expect($invoice->number)->toBe('INV-2026-0001')
        ->and($quotation->number)->toBe('QUO-2026-0001')
        ->and($invoice->issue_date->toDateString())->toBe('2026-10-15')
        // 14 days of payment terms from the settings.
        ->and($invoice->due_date->toDateString())->toBe('2026-10-29')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
});

it('refuses to convert the same quotation twice', function () {
    $quotation = quotationFor($this->client);
    $this->convert->handle($quotation);

    // One job, one invoice.
    $this->convert->handle($quotation->refresh());
})->throws(ValidationException::class);

it('leaves the quotation untouched when the conversion is refused', function () {
    $quotation = quotationFor($this->client);
    $first = $this->convert->handle($quotation);

    try {
        $this->convert->handle($quotation->refresh());
    } catch (ValidationException) {
        // expected
    }

    expect($quotation->refresh()->converted_invoice_id)->toBe($first->id)
        ->and(Invoice::query()->count())->toBe(1);
});

it('does not change the quotation when the lines are edited afterwards', function () {
    $quotation = quotationFor($this->client);
    $invoice = $this->convert->handle($quotation);

    app(UpdateInvoice::class)->handle($invoice, [
        'client_id' => $invoice->client_id,
        'issue_date' => '2026-10-15',
        'due_date' => '2026-10-29',
        'locale' => 'en',
        'items' => [
            ['description' => 'Scope berubah', 'quantity' => 1, 'unit_price' => 9_000_00],
        ],
    ]);

    // The quotation is a record of what was offered on the day.
    expect($quotation->refresh()->total)->toBe(4_500_00)
        ->and($quotation->items)->toHaveCount(2)
        ->and($invoice->refresh()->total)->toBe(9_000_00);
});
