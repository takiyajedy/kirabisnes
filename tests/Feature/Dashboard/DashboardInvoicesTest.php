<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->actingAs(User::factory()->create());
    $this->client = Client::factory()->create();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function invoiceOwing(InvoiceStatus $status, int $total, int $paid = 0, string $due = '2026-11-30'): Invoice
{
    return Invoice::factory()
        ->withStatus($status)
        ->totalling($total)
        ->dueOn($due)
        ->create(['client_id' => test()->client->id, 'paid_amount' => $paid]);
}

it('adds up what clients still owe', function () {
    invoiceOwing(InvoiceStatus::Sent, 1_000_00);
    invoiceOwing(InvoiceStatus::Partial, 2_000_00, 500_00);
    invoiceOwing(InvoiceStatus::Overdue, 800_00, 0, '2026-09-30');

    // None of these are owed: a draft has not been sent, the other two are
    // settled one way or the other.
    invoiceOwing(InvoiceStatus::Draft, 5_000_00);
    invoiceOwing(InvoiceStatus::Paid, 3_000_00, 3_000_00);
    invoiceOwing(InvoiceStatus::Void, 9_000_00);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // 1,000 + 1,500 + 800
            ->where('outstandingInvoices.amount', 3_300_00)
            ->where('outstandingInvoices.count', 3)
            ->where('outstandingInvoices.overdue_amount', 800_00)
            ->where('outstandingInvoices.overdue_count', 1),
        );
});

it('reports nothing owed when nothing is', function () {
    invoiceOwing(InvoiceStatus::Paid, 1_000_00, 1_000_00);

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('outstandingInvoices.amount', 0)
            ->where('outstandingInvoices.count', 0)
            ->has('invoicesDueSoon', 0),
        );
});

it('lists the five unpaid invoices closest to their due date, latest first', function () {
    foreach (['2026-12-01', '2026-11-01', '2026-09-01', '2026-10-20', '2026-10-25', '2027-01-01'] as $due) {
        invoiceOwing(InvoiceStatus::Sent, 100_00, 0, $due);
    }

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('invoicesDueSoon', 5)
            // The most overdue one is the most urgent.
            ->where('invoicesDueSoon.0.due_date', '2026-09-01')
            ->where('invoicesDueSoon.1.due_date', '2026-10-20')
            ->where('invoicesDueSoon.4.due_date', '2026-12-01'),
        );
});

it('marks the late ones in the list', function () {
    invoiceOwing(InvoiceStatus::Overdue, 100_00, 0, '2026-09-01');
    invoiceOwing(InvoiceStatus::Sent, 100_00, 0, '2026-11-01');

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('invoicesDueSoon.0.is_late', true)
            ->where('invoicesDueSoon.1.is_late', false),
        );
});

it('counts money owed whatever period the dashboard is showing', function () {
    // Issued in October; the dashboard is looking at last month.
    invoiceOwing(InvoiceStatus::Sent, 1_000_00);

    $this->get(route('dashboard', ['period' => 'last_month']))
        ->assertInertia(fn ($page) => $page
            ->where('outstandingInvoices.amount', 1_000_00),
        );
});

it('still reads the dashboard in a fixed number of queries with invoices on it', function () {
    Invoice::factory()->count(60)
        ->withStatus(InvoiceStatus::Sent)
        ->create(['client_id' => $this->client->id]);

    // Warm up the shared page props first.
    $this->get(route('dashboard'))->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(30);
});
