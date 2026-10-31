<?php

use App\Enums\CategoryType;
use App\Enums\RecurringInterval;
use App\Enums\RecurringKind;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Fund;
use App\Models\Project;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FundBalanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);

    $this->actingAs(User::factory()->create());
    $this->client = Client::factory()->create();
    $this->account = Account::factory()->create(['opening_balance' => 0]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** @return array<string, mixed> */
function recurringPayload(array $overrides = []): array
{
    return [
        'kind' => 'own_cost',
        'name' => 'Langganan Figma',
        'description' => null,
        // Ringgit on the wire, per section 3.1.
        'amount' => '49.90',
        'interval' => 'monthly',
        'next_date' => '2026-10-31',
        'client_id' => null,
        'project_id' => null,
        'category_id' => null,
        'account_id' => null,
        'auto_generate' => false,
        'remind_days_before' => 14,
        'is_active' => true,
        ...$overrides,
    ];
}

it('creates an own cost and reads the amount as ringgit', function () {
    $this->from(route('recurring.index'))
        ->post(route('recurring.store'), recurringPayload())
        ->assertInertiaFlash('toast.type', 'success');

    $item = RecurringItem::query()->firstOrFail();

    expect($item->name)->toBe('Langganan Figma')
        ->and($item->amount)->toBe(49_90)
        ->and($item->kind)->toBe(RecurringKind::OwnCost)
        ->and($item->interval)->toBe(RecurringInterval::Monthly)
        // The anchor comes from the date the owner chose.
        ->and($item->anchor_day)->toBe(31);
});

it('creates a client charge that drafts its own invoice', function () {
    $this->post(route('recurring.store'), recurringPayload([
        'kind' => 'client_billing',
        'client_id' => $this->client->id,
        'auto_generate' => true,
        'interval' => 'yearly',
    ]))->assertRedirect();

    $item = RecurringItem::query()->firstOrFail();

    expect($item->kind)->toBe(RecurringKind::ClientBilling)
        ->and($item->client_id)->toBe($this->client->id)
        ->and($item->auto_generate)->toBeTrue();
});

it('needs a client for a client charge', function () {
    $this->from(route('recurring.index'))
        ->post(route('recurring.store'), recurringPayload([
            'kind' => 'client_billing',
            'client_id' => null,
        ]))
        ->assertSessionHasErrors('client_id');
});

it('will not let a cost of your own draft an invoice', function () {
    // There is nobody to invoice, so the flag is dropped rather than stored.
    $this->post(route('recurring.store'), recurringPayload([
        'kind' => 'own_cost',
        'auto_generate' => true,
    ]))->assertRedirect();

    expect(RecurringItem::query()->firstOrFail()->auto_generate)->toBeFalse();
});

it('refuses a project belonging to another client', function () {
    $other = Project::factory()->create();

    $this->from(route('recurring.index'))
        ->post(route('recurring.store'), recurringPayload([
            'kind' => 'client_billing',
            'client_id' => $this->client->id,
            'project_id' => $other->id,
        ]))
        ->assertSessionHasErrors('project_id');
});

it('refuses an income category on a cost', function () {
    $income = Category::query()->where('type', CategoryType::Income)->firstOrFail();

    $this->from(route('recurring.index'))
        ->post(route('recurring.store'), recurringPayload([
            'category_id' => $income->id,
        ]))
        ->assertSessionHasErrors('category_id');
});

it('moves the anchor day when the date is edited', function () {
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-15')->create();

    $this->patch(route('recurring.update', $item), recurringPayload([
        'next_date' => '2026-11-30',
    ]))->assertRedirect();

    expect($item->refresh()->anchor_day)->toBe(30)
        ->and($item->next_date->toDateString())->toBe('2026-11-30');
});

it('lists each kind on its own tab', function () {
    RecurringItem::factory()->ownCost()->count(2)->create();
    RecurringItem::factory()->clientBilling($this->client)->count(3)->create();

    $this->get(route('recurring.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Recurring/Index')
            // Client charges are the default tab.
            ->where('kind', 'client_billing')
            ->has('items', 3)
            ->where('counts.client_billing', 3)
            ->where('counts.own_cost', 2),
        );

    $this->get(route('recurring.index', ['kind' => 'own_cost']))
        ->assertInertia(fn ($page) => $page
            ->where('kind', 'own_cost')
            ->has('items', 2),
        );
});

it('falls back to the client tab for a nonsense kind', function () {
    $this->get(route('recurring.index', ['kind' => 'something_else']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kind', 'client_billing'));
});

it('records an own cost as paid and moves it on', function () {
    $category = Category::query()->where('type', CategoryType::Expense)->firstOrFail();

    $item = RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Monthly)
        ->dueOn('2026-10-31')
        ->amount(49_90)
        ->create([
            'name' => 'Langganan Figma',
            'category_id' => $category->id,
            'account_id' => $this->account->id,
        ]);

    $this->from(route('recurring.index'))
        ->post(route('recurring.pay', $item), [
            'account_id' => $this->account->id,
            'date' => '2026-10-31',
        ])
        ->assertInertiaFlash('toast.type', 'success');

    $expense = Transaction::query()->firstOrFail();

    expect($expense->type->value)->toBe('expense')
        ->and($expense->amount)->toBe(49_90)
        ->and($expense->description)->toBe('Langganan Figma')
        ->and($expense->category_id)->toBe($category->id)
        ->and($expense->recurring_item_id)->toBe($item->id)
        ->and($expense->date->toDateString())->toBe('2026-10-31')
        // The fund the category points at is where it comes out of.
        ->and($expense->fund_id)->not->toBeNull();

    // November, and the anchor held through the shorter month.
    expect($item->refresh()->next_date->toDateString())->toBe('2026-11-30');
});

it('takes the money out of the fund the cost belongs to', function () {
    $operating = Fund::query()->where('slug', 'operating')->firstOrFail();
    $category = Category::query()
        ->where('type', CategoryType::Expense)
        ->where('default_fund_id', $operating->id)
        ->firstOrFail();

    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')
        ->amount(120_00)
        ->create(['category_id' => $category->id, 'account_id' => $this->account->id]);

    $this->post(route('recurring.pay', $item), [
        'account_id' => $this->account->id,
        'date' => '2026-10-20',
    ])->assertRedirect();

    expect(app(FundBalanceService::class)->for($operating))
        ->toBe(-120_00);
});

it('clears the reminder mark when a cost is paid', function () {
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')
        ->create(['account_id' => $this->account->id, 'last_reminded_at' => now()]);

    $this->post(route('recurring.pay', $item), [
        'account_id' => $this->account->id,
        'date' => '2026-10-20',
    ])->assertRedirect();

    // The next charge is a new period and may be reminded about.
    expect($item->refresh()->last_reminded_at)->toBeNull();
});

it('will not mark a client charge as paid', function () {
    $item = RecurringItem::factory()->clientBilling($this->client)
        ->dueOn('2026-10-20')->create();

    $this->post(route('recurring.pay', $item), [
        'account_id' => $this->account->id,
        'date' => '2026-10-20',
    ])->assertForbidden();
});

it('moves an item on without recording anything', function () {
    $item = RecurringItem::factory()->clientBilling($this->client)
        ->every(RecurringInterval::Monthly)
        ->dueOn('2026-10-31')
        ->create(['last_reminded_at' => now()]);

    $this->from(route('recurring.index'))
        ->post(route('recurring.skip', $item))
        ->assertInertiaFlash('toast.type', 'success');

    expect($item->refresh()->next_date->toDateString())->toBe('2026-11-30')
        ->and($item->last_reminded_at)->toBeNull()
        ->and(Transaction::query()->count())->toBe(0);
});

it('deletes an item but keeps what it produced', function () {
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')
        ->create(['account_id' => $this->account->id]);

    $this->post(route('recurring.pay', $item), [
        'account_id' => $this->account->id,
        'date' => '2026-10-20',
    ])->assertRedirect();

    $this->from(route('recurring.index'))
        ->delete(route('recurring.destroy', $item))
        ->assertInertiaFlash('toast.type', 'success');

    expect(RecurringItem::query()->count())->toBe(0)
        // The expense really happened, so it stays on the books.
        ->and(Transaction::query()->count())->toBe(1);
});

it('keeps guests out', function () {
    auth()->logout();

    $this->get(route('recurring.index'))->assertRedirect(route('login'));
});
