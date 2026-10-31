<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    $this->seed(FundSeeder::class);
    $this->actingAs(User::factory()->create());
    $this->client = Client::factory()->create();
});

/** @return array<string, mixed> */
function projectPayload(array $overrides = []): array
{
    return [
        'client_id' => test()->client->id,
        'name' => 'Portal Tempahan',
        'description' => null,
        // Ringgit on the wire, per section 3.1.
        'contract_amount' => '12000',
        'status' => 'active',
        'start_date' => '2026-09-01',
        'end_date' => null,
        'internal_hourly_rate' => null,
        ...$overrides,
    ];
}

it('creates a project and reads the contract amount as ringgit', function () {
    $this->from(route('projects.index'))
        ->post(route('projects.store'), projectPayload())
        ->assertRedirect(route('projects.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $project = Project::query()->firstOrFail();

    expect($project->name)->toBe('Portal Tempahan')
        ->and($project->contract_amount)->toBe(12_000_00)
        ->and($project->status)->toBe(ProjectStatus::Active);
});

it('reads the hourly rate as ringgit too', function () {
    $this->post(route('projects.store'), projectPayload([
        'internal_hourly_rate' => '85.50',
    ]))->assertRedirect();

    expect(Project::query()->value('internal_hourly_rate'))->toBe(8_550);
});

it('refuses an end date before the start date', function () {
    $this->from(route('projects.index'))
        ->post(route('projects.store'), projectPayload([
            'start_date' => '2026-09-01',
            'end_date' => '2026-08-01',
        ]))
        ->assertSessionHasErrors('end_date');
});

it('works out the gross profit from what is tagged to the project', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);
    $account = Account::factory()->create();

    Transaction::factory()->ofType(TransactionType::Income)->amount(5_000_00)
        ->create(['account_id' => $account->id, 'project_id' => $project->id]);
    Transaction::factory()->ofType(TransactionType::Expense)->amount(1_200_00)
        ->create(['account_id' => $account->id, 'project_id' => $project->id]);
    // Not tagged to the project, so it is none of its business.
    Transaction::factory()->ofType(TransactionType::Expense)->amount(9_999_00)
        ->create(['account_id' => $account->id]);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Show')
            ->where('project.summary.received', 5_000_00)
            ->where('project.summary.costs', 1_200_00)
            ->where('project.summary.gross_profit', 3_800_00)
            ->has('transactions', 2),
        );
});

it('reports a loss as a negative gross profit', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);
    $account = Account::factory()->create();

    Transaction::factory()->ofType(TransactionType::Income)->amount(500_00)
        ->create(['account_id' => $account->id, 'project_id' => $project->id]);
    Transaction::factory()->ofType(TransactionType::Expense)->amount(900_00)
        ->create(['account_id' => $account->id, 'project_id' => $project->id]);

    $this->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('project.summary.gross_profit', -400_00),
        );
});

it('counts what is invoiced and still owed on the project', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);

    Invoice::factory()->withStatus(InvoiceStatus::Partial)
        ->totalling(3_000_00)
        ->create([
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'paid_amount' => 1_000_00,
        ]);

    $this->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('project.summary.invoiced', 3_000_00)
            ->where('project.summary.outstanding', 2_000_00),
        );
});

it('leaves voided invoices out of the project figures', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);

    Invoice::factory()->withStatus(InvoiceStatus::Void)
        ->totalling(9_000_00)
        ->create(['client_id' => $this->client->id, 'project_id' => $project->id]);

    $this->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('project.summary.invoiced', 0));
});

it('filters the list by status and client', function () {
    $other = Client::factory()->create();

    Project::factory()->withStatus(ProjectStatus::Active)
        ->create(['client_id' => $this->client->id, 'name' => 'Aktif']);
    Project::factory()->withStatus(ProjectStatus::Completed)
        ->create(['client_id' => $other->id, 'name' => 'Siap']);

    $this->get(route('projects.index', ['status' => 'active']))
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Aktif'),
        );

    $this->get(route('projects.index', ['client_id' => $other->id]))
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Siap'),
        );
});

it('refuses to delete a project that has transactions', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);
    Transaction::factory()->create([
        'account_id' => Account::factory()->create()->id,
        'project_id' => $project->id,
    ]);

    $this->from(route('projects.index'))
        ->delete(route('projects.destroy', $project))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Project::query()->count())->toBe(1);
});

it('deletes a project with nothing against it', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);

    $this->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->count())->toBe(0);
});
