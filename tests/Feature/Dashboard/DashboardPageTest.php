<?php

use App\Enums\Locale;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->actingAs(User::factory()->create(['locale' => Locale::Malay]));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('renders the dashboard with every figure it needs', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('period.period', 'this_month')
            ->where('period.from', '2026-10-01')
            ->where('period.to', '2026-10-31')
            ->has('periodOptions', 4)
            ->has('summary')
            ->has('funds', 5)
            ->has('monthly', 12)
            ->has('expensesByCategory')
            ->has('recent')
            ->where('totalBalance', 0),
        );
});

it('switches to the window asked for in the query string', function () {
    $this->get(route('dashboard', ['period' => 'last_month']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('period.period', 'last_month')
            ->where('period.from', '2026-09-01')
            ->where('period.to', '2026-09-30'),
        );
});

it('accepts a custom range', function () {
    $this->get(route('dashboard', [
        'period' => 'custom',
        'from' => '2026-07-01',
        'to' => '2026-07-15',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('period.period', 'custom')
            ->where('period.from', '2026-07-01')
            ->where('period.to', '2026-07-15'),
        );
});

it('shows the current month rather than failing on a nonsense query string', function () {
    $this->get(route('dashboard', ['period' => 'since_forever', 'from' => 'yesterday-ish']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('period.from', '2026-10-01'));
});

it('names what each figure is compared against', function () {
    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('period.comparison_label', 'berbanding bulan lepas'),
        );

    $this->get(route('dashboard', ['period' => 'this_year']))
        ->assertInertia(fn ($page) => $page
            ->where('period.comparison_label', 'berbanding tahun lepas'),
        );

    $this->get(route('dashboard', ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertInertia(fn ($page) => $page
            ->where('period.comparison_label', 'berbanding tempoh sebelumnya'),
        );
});

it('names the funds and categories in the reader language', function () {
    $this->actingAs(User::factory()->create(['locale' => Locale::English]));

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('funds.1.name', 'Owner Pay'));
});

it('reads the whole dashboard in a fixed number of queries', function () {
    // The page must not grow a query per transaction, per fund or per account:
    // that is what would break the load time the plan asks for.
    $account = Account::factory()->create();

    Transaction::factory()->count(120)->create([
        'account_id' => $account->id,
        'date' => '2026-10-10',
    ]);

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(25);
});

it('keeps the query count flat as the transactions pile up', function () {
    $account = Account::factory()->create();

    $measure = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // One request first: the shared page props cache a lookup or two, and
    // that warm-up is not what this test is about.
    $this->get(route('dashboard'))->assertOk();

    Transaction::factory()->count(20)->create([
        'account_id' => $account->id,
        'date' => '2026-10-10',
    ]);
    $small = $measure();

    Transaction::factory()->count(400)->create([
        'account_id' => $account->id,
        'date' => '2026-10-11',
    ]);
    $large = $measure();

    expect($large)->toBe($small);
});

it('is closed to guests', function () {
    auth()->logout();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
