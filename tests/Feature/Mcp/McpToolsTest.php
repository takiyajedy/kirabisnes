<?php

use App\Actions\Transactions\StoreTransaction;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Mcp\Servers\KiraBisnesServer;
use App\Mcp\Tools\CreateInvoiceTool;
use App\Mcp\Tools\FundBalancesTool;
use App\Mcp\Tools\ProfitLossTool;
use App\Mcp\Tools\RecordInvoicePaymentTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Mcp\Tools\SendInvoiceTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\FundBalanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\PendingTestResponse;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-04 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->income = Category::query()->whereJsonContains('name->en', 'Web Project')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function mcp(): PendingTestResponse
{
    return KiraBisnesServer::actingAs(test()->owner);
}

it('reports amounts in ringgit, never sen', function () {
    // An agent told "12000" would read it as twelve thousand ringgit.
    app(StoreTransaction::class)->handle([
        'type' => 'income',
        'account_id' => $this->account->id,
        'category_id' => $this->income->id,
        'amount' => 1_000_00,
        'date' => '2026-11-04',
        'description' => 'Ringgit check',
    ]);

    mcp()->tool(FundBalancesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('currency', 'MYR')
            ->where('total', 1000)
            ->etc());
});

it('matches the figures the app itself reports', function () {
    app(StoreTransaction::class)->handle([
        'type' => 'income',
        'account_id' => $this->account->id,
        'category_id' => $this->income->id,
        'amount' => 5_000_00,
        'date' => '2026-03-01',
        'description' => 'Project',
    ]);

    mcp()->tool(ProfitLossTool::class, ['period' => 'this_year'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('income', 5000)
            ->where('net_profit', 5000)
            ->etc());
});

it('records a transaction and splits it across the funds', function () {
    $before = app(FundBalanceService::class)->total();

    mcp()->tool(RecordTransactionTool::class, [
        'type' => 'income',
        'amount' => 500.00,
        'date' => '2026-11-04',
        'description' => 'Recorded through MCP',
        'account_id' => $this->account->id,
        'category_id' => $this->income->id,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('recorded', true)
        ->where('amount', 500)
        // The date comes back as the day that was sent, not a UTC timestamp.
        ->where('date', '2026-11-04')
        ->has('fund_split', 5)
        ->etc());

    expect(app(FundBalanceService::class)->total() - $before)->toBe(500_00);
});

it('refuses a transaction that is not a positive amount', function () {
    mcp()->tool(RecordTransactionTool::class, [
        'type' => 'expense',
        'amount' => -50,
        'date' => '2026-11-04',
        'description' => 'Negative',
        'account_id' => $this->account->id,
    ])->assertHasErrors();
});

it('creates an invoice as a draft and sends nothing', function () {
    Mail::fake();

    $client = Client::factory()->create(['email' => 'client@example.com']);

    mcp()->tool(CreateInvoiceTool::class, [
        'client_id' => $client->id,
        'items' => [
            ['description' => 'Website', 'quantity' => 1, 'unit_price' => 4500.00],
            ['description' => 'Hosting', 'quantity' => 12, 'unit_price' => 50.00],
        ],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('created', true)
        ->where('status', InvoiceStatus::Draft->value)
        ->where('total', 5100)
        ->etc());

    Mail::assertNothingSent();
});

it('will not send an invoice without an explicit confirmation', function () {
    Mail::fake();

    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

    mcp()->tool(SendInvoiceTool::class, ['invoice_id' => $invoice->id, 'confirm' => false])
        ->assertHasErrors();

    Mail::assertNothingSent();
});

it('will not send to a client with no email on file', function () {
    Mail::fake();

    $client = Client::factory()->create(['email' => null]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => InvoiceStatus::Draft]);

    mcp()->tool(SendInvoiceTool::class, ['invoice_id' => $invoice->id, 'confirm' => true])
        ->assertHasErrors();

    Mail::assertNothingSent();
});

it('sends the invoice once confirmed and marks it sent', function () {
    Mail::fake();

    $client = Client::factory()->create(['email' => 'client@example.com']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => InvoiceStatus::Draft]);

    mcp()->tool(SendInvoiceTool::class, ['invoice_id' => $invoice->id, 'confirm' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('sent', true)
            ->where('to', 'client@example.com')
            ->etc());

    Mail::assertSent(InvoiceMail::class);
    expect($invoice->fresh()->status)->not->toBe(InvoiceStatus::Draft);
});

it('refuses a payment larger than the amount still owed', function () {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Sent,
        'subtotal' => 1_000_00, 'discount' => 0, 'total' => 1_000_00, 'paid_amount' => 0,
    ]);

    mcp()->tool(RecordInvoicePaymentTool::class, [
        'invoice_id' => $invoice->id,
        'amount' => 1500.00,
        'account_id' => $this->account->id,
    ])->assertHasErrors();

    expect($invoice->fresh()->paid_amount)->toBe(0);
});

it('records a part payment and leaves the rest owing', function () {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Sent,
        'subtotal' => 1_000_00, 'discount' => 0, 'total' => 1_000_00, 'paid_amount' => 0,
    ]);

    mcp()->tool(RecordInvoicePaymentTool::class, [
        'invoice_id' => $invoice->id,
        'amount' => 400.00,
        'account_id' => $this->account->id,
        'date' => '2026-11-04',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('recorded', true)
        ->where('amount', 400)
        ->where('still_owed', 600)
        ->etc());

    expect($invoice->fresh()->paid_amount)->toBe(400_00);
});
