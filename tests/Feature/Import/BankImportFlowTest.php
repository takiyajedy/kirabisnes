<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Fund;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FundBalanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\FundSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->actingAs(User::factory()->create());
    $this->account = Account::factory()->create(['opening_balance' => 0]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function uploadedStatement(string $csv): UploadedFile
{
    return UploadedFile::fake()->createWithContent('penyata.csv', $csv);
}

/** @return array<string, mixed> */
function uploadPayload(string $csv, array $overrides = []): array
{
    return [
        'statement' => uploadedStatement($csv),
        'account_id' => test()->account->id,
        'has_headers' => true,
        'date_format' => 'auto',
        'date_column' => 0,
        'description_column' => 1,
        'amount_column' => 2,
        ...$overrides,
    ];
}

it('shows the upload form', function () {
    $this->get(route('import.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Import/Index')
            ->has('accounts', 1)
            ->has('dateFormats', 4)
            ->where('matchDays', 3)
            ->where('preview', null),
        );
});

it('reads an uploaded statement into a preview', function () {
    $this->post(route('import.preview'), uploadPayload(
        "Date,Description,Amount\n10/10/2026,Bayaran klien,3000.00\n11/10/2026,Hosting,-120.50"
    ))->assertRedirect(route('import.index'));

    $this->get(route('import.index'))
        ->assertInertia(fn ($page) => $page
            ->has('preview.rows', 2)
            ->where('preview.account_name', $this->account->name)
            ->where('preview.counts.new', 2)
            ->where('preview.rows.0.description', 'Bayaran klien')
            ->where('preview.rows.0.amount', 300_000)
            ->where('preview.rows.0.type', 'income'),
        );

    // Nothing is written until the owner has seen it.
    expect(Transaction::query()->count())->toBe(0);
});

it('refuses a file that is not a CSV', function () {
    $this->from(route('import.index'))
        ->post(route('import.preview'), [
            ...uploadPayload('x'),
            'statement' => UploadedFile::fake()->create('penyata.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('statement');
});

it('says so when the file has nothing in it', function () {
    $this->from(route('import.index'))
        ->post(route('import.preview'), uploadPayload("Date,Description,Amount\n"))
        ->assertInertiaFlash('toast.type', 'error');
});

it('imports the rows the owner ticked', function () {
    $this->post(route('import.preview'), uploadPayload(
        "Date,Description,Amount\n10/10/2026,Satu,-10.00\n11/10/2026,Dua,-20.00"
    ));

    $this->post(route('import.store'), ['rows' => [2]])
        ->assertRedirect(route('transactions.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(Transaction::query()->pluck('description')->all())->toBe(['Dua']);
});

it('forgets the preview once it has been acted on', function () {
    $this->post(route('import.preview'), uploadPayload(
        "Date,Description,Amount\n10/10/2026,Satu,-10.00"
    ));
    $this->post(route('import.store'), ['rows' => [1]]);

    $this->get(route('import.index'))
        ->assertInertia(fn ($page) => $page->where('preview', null));
});

it('says so when the preview has gone', function () {
    // The session is where it lived, and a statement should not be left
    // lying around on disk.
    $this->from(route('import.index'))
        ->post(route('import.store'), ['rows' => [1]])
        ->assertRedirect(route('import.index'))
        ->assertInertiaFlash('toast.type', 'error');
});

it('throws the preview away when asked', function () {
    $this->post(route('import.preview'), uploadPayload(
        "Date,Description,Amount\n10/10/2026,Satu,-10.00"
    ));

    $this->delete(route('import.destroy'))->assertRedirect(route('import.index'));

    $this->get(route('import.index'))
        ->assertInertia(fn ($page) => $page->where('preview', null));

    expect(Transaction::query()->count())->toBe(0);
});

it('splits an imported income across the funds', function () {
    $this->post(route('import.preview'), uploadPayload(
        "Date,Description,Amount\n10/10/2026,Bayaran klien,3000.00"
    ));
    $this->post(route('import.store'), ['rows' => [1]]);

    // An imported income is income: the funds take their share of it.
    $operating = Fund::query()->where('slug', 'operating')->firstOrFail();

    expect(app(FundBalanceService::class)->for($operating))
        ->toBe(900_00);
});

it('writes an audit line for an imported row', function () {
    $this->post(route('import.preview'), uploadPayload(
        "Date,Description,Amount\n10/10/2026,Bayaran klien,3000.00"
    ));
    $this->post(route('import.store'), ['rows' => [1]]);

    expect(AuditLog::query()->where('event', 'created')->count())->toBe(1);
});

it('keeps guests out', function () {
    auth()->logout();

    $this->get(route('import.index'))->assertRedirect(route('login'));
});
