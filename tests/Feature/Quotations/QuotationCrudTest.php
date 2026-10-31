<?php

use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->actingAs(User::factory()->create());
    $this->client = Client::factory()->create();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** @return array<string, mixed> */
function quotationPayload(array $overrides = []): array
{
    return [
        'client_id' => test()->client->id,
        'project_id' => null,
        'issue_date' => '2026-10-01',
        'valid_until' => '2026-10-31',
        'status' => 'draft',
        'locale' => 'ms',
        // Ringgit on the wire, per section 3.1.
        'discount' => '0',
        'notes' => null,
        'terms' => null,
        'items' => [
            ['description' => 'Reka bentuk', 'quantity' => 1, 'unit_price' => '1500'],
        ],
        ...$overrides,
    ];
}

it('creates a quotation and prices the lines on the server', function () {
    $this->post(route('quotations.store'), quotationPayload([
        'items' => [
            ['description' => 'Reka bentuk', 'quantity' => 2, 'unit_price' => '750.50'],
            ['description' => 'Latihan', 'quantity' => '1.5', 'unit_price' => '400'],
        ],
    ]))->assertRedirect();

    $quotation = Quotation::query()->with('items')->firstOrFail();

    expect($quotation->items[0]->amount)->toBe(1_501_00)
        ->and($quotation->items[1]->amount)->toBe(600_00)
        ->and($quotation->subtotal)->toBe(2_101_00)
        ->and($quotation->total)->toBe(2_101_00);
});

it('reads the discount as ringgit', function () {
    $this->post(route('quotations.store'), quotationPayload(['discount' => '250.75']))
        ->assertRedirect();

    $quotation = Quotation::query()->firstOrFail();

    expect($quotation->discount)->toBe(25_075)
        ->and($quotation->total)->toBe(1_500_00 - 25_075);
});

it('needs at least one line', function () {
    $this->from(route('quotations.create'))
        ->post(route('quotations.store'), quotationPayload(['items' => []]))
        ->assertSessionHasErrors('items');
});

it('rejects a line with no description or a zero quantity', function () {
    $this->from(route('quotations.create'))
        ->post(route('quotations.store'), quotationPayload([
            'items' => [['description' => '', 'quantity' => 0, 'unit_price' => '100']],
        ]))
        ->assertSessionHasErrors(['items.0.description', 'items.0.quantity']);
});

it('refuses a validity date before the issue date', function () {
    $this->from(route('quotations.create'))
        ->post(route('quotations.store'), quotationPayload([
            'issue_date' => '2026-10-10',
            'valid_until' => '2026-10-01',
        ]))
        ->assertSessionHasErrors('valid_until');
});

it('refuses a project belonging to another client', function () {
    // Tagging someone else's project would put the document on the wrong books.
    $other = Project::factory()->create();

    $this->from(route('quotations.create'))
        ->post(route('quotations.store'), quotationPayload(['project_id' => $other->id]))
        ->assertSessionHasErrors('project_id');
});

it('accepts a project belonging to this client', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);

    $this->post(route('quotations.store'), quotationPayload(['project_id' => $project->id]))
        ->assertRedirect();

    expect(Quotation::query()->value('project_id'))->toBe($project->id);
});

it('replaces the lines when a quotation is edited', function () {
    $this->post(route('quotations.store'), quotationPayload())->assertRedirect();
    $quotation = Quotation::query()->firstOrFail();

    $this->patch(route('quotations.update', $quotation), quotationPayload([
        'items' => [
            ['description' => 'Skop baharu', 'quantity' => 1, 'unit_price' => '3000'],
        ],
    ]))->assertRedirect();

    $quotation->refresh()->load('items');

    expect($quotation->items)->toHaveCount(1)
        ->and($quotation->items[0]->description)->toBe('Skop baharu')
        ->and($quotation->total)->toBe(3_000_00);
});

it('keeps the number when a quotation is edited', function () {
    $this->post(route('quotations.store'), quotationPayload())->assertRedirect();
    $quotation = Quotation::query()->firstOrFail();
    $number = $quotation->number;

    $this->patch(route('quotations.update', $quotation), quotationPayload([
        'number' => 'QUO-9999-9999',
    ]))->assertRedirect();

    expect($quotation->refresh()->number)->toBe($number);
});

it('moves a quotation along without opening the form', function () {
    $quotation = Quotation::factory()->create(['client_id' => $this->client->id]);

    $this->from(route('quotations.show', $quotation))
        ->patch(route('quotations.status', $quotation), ['status' => 'rejected'])
        ->assertInertiaFlash('toast.type', 'success');

    expect($quotation->refresh()->status)->toBe(QuotationStatus::Rejected);
});

it('will not edit or restatus a quotation that became an invoice', function () {
    $quotation = Quotation::factory()
        ->withStatus(QuotationStatus::Accepted)
        ->create([
            'client_id' => $this->client->id,
            'converted_invoice_id' => Invoice::factory()
                ->create(['client_id' => $this->client->id])->id,
        ]);

    $this->get(route('quotations.edit', $quotation))->assertForbidden();
    $this->patch(route('quotations.update', $quotation), quotationPayload())
        ->assertForbidden();
    $this->patch(route('quotations.status', $quotation), ['status' => 'draft'])
        ->assertForbidden();
    $this->delete(route('quotations.destroy', $quotation))->assertForbidden();
});

it('shows the next number on the create form without taking it', function () {
    $this->get(route('quotations.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Form')
            ->where('nextNumber', 'QUO-2026-0001')
            ->where('quotation', null),
        );

    // Opening the form twice must not burn a number.
    $this->get(route('quotations.create'))
        ->assertInertia(fn ($page) => $page->where('nextNumber', 'QUO-2026-0001'));
});

it('fills in the client when linked from a client page', function () {
    $this->get(route('quotations.create', ['client_id' => $this->client->id]))
        ->assertInertia(fn ($page) => $page
            ->where('preset.client_id', $this->client->id),
        );
});

it('filters the list by status, client and number', function () {
    $other = Client::factory()->create();

    Quotation::factory()->withStatus(QuotationStatus::Sent)
        ->create(['client_id' => $this->client->id, 'number' => 'QUO-2026-0100']);
    Quotation::factory()->withStatus(QuotationStatus::Draft)
        ->create(['client_id' => $other->id, 'number' => 'QUO-2026-0200']);

    $this->get(route('quotations.index', ['status' => 'sent']))
        ->assertInertia(fn ($page) => $page->has('quotations.data', 1));

    $this->get(route('quotations.index', ['client_id' => $other->id]))
        ->assertInertia(fn ($page) => $page
            ->where('quotations.data.0.number', 'QUO-2026-0200'),
        );

    $this->get(route('quotations.index', ['search' => '0100']))
        ->assertInertia(fn ($page) => $page
            ->where('quotations.data.0.number', 'QUO-2026-0100'),
        );
});

it('flags a quotation whose validity has passed', function () {
    $quotation = Quotation::factory()->withStatus(QuotationStatus::Sent)
        ->create([
            'client_id' => $this->client->id,
            'valid_until' => '2026-09-30',
        ]);

    $this->get(route('quotations.show', $quotation))
        ->assertInertia(fn ($page) => $page->where('quotation.has_lapsed', true));
});
