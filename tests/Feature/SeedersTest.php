<?php

use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Fund;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('seeds the owner account', function () {
    $user = User::query()->where('email', 'admin@kirabisnes.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->locale->value)->toBe('ms')
        ->and($user->appearance->value)->toBe('system')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('seeds five system funds that add up to 100 percent', function () {
    $funds = Fund::query()->orderBy('sort_order')->get();

    expect($funds)->toHaveCount(5)
        ->and($funds->pluck('slug')->all())->toBe([
            'operating', 'owner_pay', 'tax', 'emergency', 'capital',
        ])
        ->and((float) $funds->sum('percentage'))->toBe(100.0)
        ->and($funds->every(fn (Fund $fund) => $fund->is_system))->toBeTrue();
});

it('seeds fund names in both languages', function () {
    $fund = Fund::query()->where('slug', 'owner_pay')->first();

    expect($fund->getTranslation('name', 'ms'))->toBe('Gaji Sendiri')
        ->and($fund->getTranslation('name', 'en'))->toBe('Owner Pay');
});

it('seeds income and expense categories in both languages', function () {
    $income = Category::query()->where('type', CategoryType::Income)->get();
    $expense = Category::query()->where('type', CategoryType::Expense)->get();

    expect($income)->toHaveCount(6)
        ->and($expense)->toHaveCount(11);

    $hosting = $expense->first(
        fn (Category $category) => $category->getTranslation('name', 'en') === 'Hosting & Server',
    );

    expect($hosting)->not->toBeNull()
        ->and($hosting->getTranslation('name', 'ms'))->toBe('Hosting & Server')
        ->and($hosting->tax_deductible_default)->toBeTrue()
        ->and($hosting->defaultFund->slug)->toBe('operating');
});

it('points hardware spending at the capital fund', function () {
    $hardware = Category::query()
        ->where('type', CategoryType::Expense)
        ->get()
        ->first(fn (Category $c) => $c->getTranslation('name', 'en') === 'Hardware');

    expect($hardware->defaultFund->slug)->toBe('capital');
});

it('seeds one cash account', function () {
    $accounts = Account::query()->get();

    expect($accounts)->toHaveCount(1)
        ->and($accounts->first()->type)->toBe(AccountType::Cash)
        ->and($accounts->first()->opening_balance)->toBe(0);
});

it('seeds the business profile defaults', function () {
    expect(Setting::get('invoice_prefix'))->toBe('INV')
        ->and(Setting::get('quotation_prefix'))->toBe('QUO')
        ->and(Setting::get('default_payment_terms_days'))->toBe(14)
        ->and(Setting::get('default_document_locale'))->toBe('ms');
});

it('can be run twice without duplicating anything', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1)
        ->and(Fund::query()->count())->toBe(5)
        ->and(Category::query()->count())->toBe(17)
        ->and(Account::query()->count())->toBe(1);
});
