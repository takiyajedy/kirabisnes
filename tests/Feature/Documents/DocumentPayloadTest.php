<?php

use App\Actions\Invoices\RecordInvoicePayment;
use App\Actions\Invoices\StoreInvoice;
use App\Actions\Quotations\StoreQuotation;
use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;

/**
 * The shape of what the pages receive.
 *
 * A nested resource collection serialises to {"data": [...]} unless it is
 * resolved, which the pages cannot iterate — and nothing fails loudly: the
 * rows simply render as blanks and NaN. These tests pin the shape down.
 */
beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);

    $this->actingAs(User::factory()->create());
    $this->account = Account::factory()->create();
    $this->client = Client::factory()->create();
});

it('sends the invoice line items as a plain list', function () {
    $invoice = app(StoreInvoice::class)->handle([
        'client_id' => $this->client->id,
        'issue_date' => '2026-10-01',
        'locale' => 'ms',
        'items' => [
            ['description' => 'Reka bentuk', 'quantity' => 2, 'unit_price' => 750_00],
            ['description' => 'Latihan', 'quantity' => '1.5', 'unit_price' => 400_00],
        ],
    ]);

    $this->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('invoice.items', 2)
            ->where('invoice.items.0.description', 'Reka bentuk')
            ->where('invoice.items.0.quantity', 2)
            ->where('invoice.items.0.unit_price', 750_00)
            ->where('invoice.items.0.amount', 1_500_00)
            ->where('invoice.items.1.quantity', 1.5)
            ->where('invoice.items.1.amount', 600_00),
        );
});

it('sends the payments as a plain list', function () {
    $invoice = app(StoreInvoice::class)->handle([
        'client_id' => $this->client->id,
        'issue_date' => '2026-10-01',
        'status' => InvoiceStatus::Sent->value,
        'locale' => 'ms',
        'items' => [['description' => 'Kerja', 'quantity' => 1, 'unit_price' => 1_000_00]],
    ]);

    app(RecordInvoicePayment::class)->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 400_00,
        'date' => '2026-10-10',
    ]);

    $this->get(route('invoices.show', $invoice))
        ->assertInertia(fn ($page) => $page
            ->has('invoice.payments', 1)
            ->where('invoice.payments.0.amount', 400_00)
            ->where('invoice.payments.0.date', '2026-10-10')
            ->where('invoice.payments.0.account_name', $this->account->name),
        );
});

it('sends the quotation line items as a plain list', function () {
    $quotation = app(StoreQuotation::class)->handle([
        'client_id' => $this->client->id,
        'issue_date' => '2026-10-01',
        'valid_until' => '2026-10-31',
        'locale' => 'ms',
        'items' => [['description' => 'Skop', 'quantity' => 3, 'unit_price' => 200_00]],
    ]);

    $this->get(route('quotations.show', $quotation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('quotation.items', 1)
            ->where('quotation.items.0.description', 'Skop')
            ->where('quotation.items.0.amount', 600_00),
        );
});

it('sends the edit form the items it has to put back in the fields', function () {
    $invoice = app(StoreInvoice::class)->handle([
        'client_id' => $this->client->id,
        'issue_date' => '2026-10-01',
        'locale' => 'ms',
        'items' => [['description' => 'Kerja', 'quantity' => 1, 'unit_price' => 500_00]],
    ]);

    $this->get(route('invoices.edit', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Invoices/Form')
            ->has('invoice.items', 1)
            ->where('invoice.items.0.description', 'Kerja'),
        );
});
