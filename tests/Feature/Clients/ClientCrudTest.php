<?php

use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['locale' => Locale::Malay]));
});

/** @return array<string, mixed> */
function clientPayload(array $overrides = []): array
{
    return [
        'name' => 'Ahmad bin Ali',
        'company' => 'Syarikat Maju Sdn Bhd',
        'email' => 'ahmad@maju.test',
        'phone' => '012-3456789',
        'address' => "No 1, Jalan Besar\n50000 Kuala Lumpur",
        'reg_no' => '202301012345',
        'notes' => null,
        'preferred_locale' => 'ms',
        ...$overrides,
    ];
}

it('lists clients with what they owe', function () {
    $client = Client::factory()->create(['name' => 'Zul']);
    Invoice::factory()->withStatus(InvoiceStatus::Sent)
        ->totalling(1_000_00)
        ->create(['client_id' => $client->id, 'paid_amount' => 400_00]);

    $this->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'Zul')
            ->where('clients.data.0.summary.invoiced', 1_000_00)
            ->where('clients.data.0.summary.paid', 400_00)
            ->where('clients.data.0.summary.outstanding', 600_00),
        );
});

it('creates a client', function () {
    $this->from(route('clients.index'))
        ->post(route('clients.store'), clientPayload())
        ->assertRedirect(route('clients.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $client = Client::query()->firstOrFail();

    expect($client->name)->toBe('Ahmad bin Ali')
        ->and($client->company)->toBe('Syarikat Maju Sdn Bhd')
        ->and($client->preferred_locale)->toBe(Locale::Malay);
});

it('remembers that a client reads English', function () {
    $this->post(route('clients.store'), clientPayload([
        'preferred_locale' => 'en',
    ]))->assertRedirect();

    expect(Client::query()->firstOrFail()->preferred_locale)->toBe(Locale::English);
});

it('requires a name and a known language', function () {
    $this->from(route('clients.index'))
        ->post(route('clients.store'), clientPayload([
            'name' => '',
            'preferred_locale' => 'fr',
        ]))
        ->assertSessionHasErrors(['name', 'preferred_locale']);
});

it('rejects an address that is not an e-mail', function () {
    $this->from(route('clients.index'))
        ->post(route('clients.store'), clientPayload(['email' => 'bukan-emel']))
        ->assertSessionHasErrors('email');
});

it('updates a client', function () {
    $client = Client::factory()->create();

    $this->patch(route('clients.update', $client), clientPayload([
        'name' => 'Nama Baharu',
    ]))->assertRedirect();

    expect($client->refresh()->name)->toBe('Nama Baharu');
});

it('shows a client with their projects and documents', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
    ]);

    $this->get(route('clients.show', $client))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->where('client.id', $client->id)
            ->has('projects', 1)
            ->has('invoices', 1)
            ->has('quotations', 0)
            ->has('client.summary'),
        );
});

it('deletes a client who has nothing against them', function () {
    $client = Client::factory()->create();

    $this->delete(route('clients.destroy', $client))
        ->assertRedirect(route('clients.index'));

    expect(Client::query()->count())->toBe(0);
});

it('refuses to delete a client with a project', function () {
    $client = Client::factory()->create();
    Project::factory()->create(['client_id' => $client->id]);

    $this->from(route('clients.index'))
        ->delete(route('clients.destroy', $client))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Client::query()->count())->toBe(1);
});

it('refuses to delete a client with an invoice', function () {
    $client = Client::factory()->create();
    Invoice::factory()->create(['client_id' => $client->id]);

    $this->from(route('clients.index'))
        ->delete(route('clients.destroy', $client))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Client::query()->count())->toBe(1);
});

it('finds a client by name, company or e-mail', function () {
    Client::factory()->create(['name' => 'Siti', 'company' => 'Alpha Sdn Bhd', 'email' => 'siti@alpha.test']);
    Client::factory()->create(['name' => 'Bakar', 'company' => 'Beta Enterprise', 'email' => 'bakar@beta.test']);

    $this->get(route('clients.index', ['search' => 'Alpha']))
        ->assertInertia(fn ($page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'Siti'),
        );

    $this->get(route('clients.index', ['search' => 'bakar@beta']))
        ->assertInertia(fn ($page) => $page->has('clients.data', 1));
});

it('keeps guests out', function () {
    auth()->logout();

    $this->get(route('clients.index'))->assertRedirect(route('login'));
});
