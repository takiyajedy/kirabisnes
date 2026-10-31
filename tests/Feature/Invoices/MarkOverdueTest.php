<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 01:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('marks a sent invoice overdue once the due date has gone by', function () {
    $invoice = Invoice::factory()
        ->withStatus(InvoiceStatus::Sent)
        ->dueOn('2026-10-14')
        ->create();

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Overdue);
});

it('leaves an invoice due today alone', function () {
    // Due today means due by the end of today.
    $invoice = Invoice::factory()
        ->withStatus(InvoiceStatus::Sent)
        ->dueOn('2026-10-15')
        ->create();

    $this->artisan('invoices:mark-overdue');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Sent);
});

it('marks a part paid invoice overdue too', function () {
    $invoice = Invoice::factory()
        ->withStatus(InvoiceStatus::Partial)
        ->dueOn('2026-09-30')
        ->create(['paid_amount' => 100_00]);

    $this->artisan('invoices:mark-overdue');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Overdue);
});

it('leaves drafts, paid and voided invoices alone', function () {
    $draft = Invoice::factory()->withStatus(InvoiceStatus::Draft)->dueOn('2026-01-01')->create();
    $paid = Invoice::factory()->withStatus(InvoiceStatus::Paid)->dueOn('2026-01-01')->create();
    $void = Invoice::factory()->withStatus(InvoiceStatus::Void)->dueOn('2026-01-01')->create();

    $this->artisan('invoices:mark-overdue');

    expect($draft->refresh()->status)->toBe(InvoiceStatus::Draft)
        ->and($paid->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($void->refresh()->status)->toBe(InvoiceStatus::Void);
});

it('clears overdue when the due date is pushed back', function () {
    $invoice = Invoice::factory()
        ->withStatus(InvoiceStatus::Overdue)
        ->dueOn('2026-11-30')
        ->create();

    $this->artisan('invoices:mark-overdue');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Sent);
});

it('reports how many it changed', function () {
    Invoice::factory()->count(3)
        ->withStatus(InvoiceStatus::Sent)
        ->dueOn('2026-09-01')
        ->create();

    $this->artisan('invoices:mark-overdue')
        ->expectsOutputToContain('Marked 3 invoice(s) overdue');
});
