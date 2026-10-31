<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Fund;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    $this->seed(FundSeeder::class);
    $this->actingAs(User::factory()->create());
});

/** @param array<string, mixed> $overrides */
function categoryPayload(array $overrides = []): array
{
    return [
        'name_ms' => 'Kursus & Buku',
        'name_en' => 'Courses & Books',
        'type' => 'expense',
        'default_fund_id' => null,
        'tax_deductible_default' => false,
        'color' => '#059669',
        ...$overrides,
    ];
}

it('lists categories with their usage count', function () {
    $category = Category::factory()->create();
    Transaction::factory()->count(2)->create(['category_id' => $category->id]);

    $this->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Categories')
            ->has('categories', 1)
            ->where('categories.0.transactions_count', 2)
            ->has('funds', 5)
            ->has('categoryTypes', 2),
        );
});

it('creates a category with both names', function () {
    $this->from(route('categories.index'))
        ->post(route('categories.store'), categoryPayload())
        ->assertRedirect(route('categories.index'));

    $category = Category::query()->firstOrFail();

    expect($category->getTranslation('name', 'ms'))->toBe('Kursus & Buku')
        ->and($category->getTranslation('name', 'en'))->toBe('Courses & Books')
        ->and($category->type)->toBe(CategoryType::Expense)
        ->and($category->is_system)->toBeFalse();
});

it('falls back to the Malay name when English is left blank', function () {
    $this->from(route('categories.index'))
        ->post(route('categories.store'), categoryPayload(['name_en' => '']));

    $category = Category::query()->firstOrFail();

    expect($category->getTranslation('name', 'en'))->toBe('Kursus & Buku');
});

it('requires the Malay name', function () {
    $this->from(route('categories.index'))
        ->post(route('categories.store'), categoryPayload(['name_ms' => '']))
        ->assertSessionHasErrors('name_ms');
});

it('rejects a colour that is not a hex code', function () {
    $this->from(route('categories.index'))
        ->post(route('categories.store'), categoryPayload(['color' => 'emerald']))
        ->assertSessionHasErrors('color');
});

it('stores the default fund for an expense category', function () {
    $capital = Fund::query()->where('slug', 'capital')->firstOrFail();

    $this->from(route('categories.index'))->post(route('categories.store'), categoryPayload([
        'default_fund_id' => $capital->id,
        'tax_deductible_default' => true,
    ]));

    $category = Category::query()->firstOrFail();

    expect($category->default_fund_id)->toBe($capital->id)
        ->and($category->tax_deductible_default)->toBeTrue();
});

it('updates a category', function () {
    $category = Category::factory()->create();

    $this->from(route('categories.index'))
        ->patch(route('categories.update', $category), categoryPayload([
            'name_ms' => 'Nama Baharu',
            'name_en' => 'New Name',
            'type' => 'income',
        ]))
        ->assertRedirect(route('categories.index'));

    $category->refresh();

    expect($category->getTranslation('name', 'ms'))->toBe('Nama Baharu')
        ->and($category->type)->toBe(CategoryType::Income);
});

it('keeps the type of a built-in category, because transactions rely on it', function () {
    $category = Category::factory()->system()->create();

    $this->from(route('categories.index'))
        ->patch(route('categories.update', $category), categoryPayload([
            'name_ms' => 'Diubah',
            'type' => 'income',
        ]))
        ->assertRedirect(route('categories.index'));

    $category->refresh();

    expect($category->getTranslation('name', 'ms'))->toBe('Diubah')
        ->and($category->type)->toBe(CategoryType::Expense);
});

it('deletes an unused category', function () {
    $category = Category::factory()->create();

    $this->from(route('categories.index'))
        ->delete(route('categories.destroy', $category))
        ->assertInertiaFlash('toast.type', 'success');

    expect(Category::query()->count())->toBe(0);
});

it('refuses to delete a built-in category', function () {
    $category = Category::factory()->system()->create();

    $this->from(route('categories.index'))
        ->delete(route('categories.destroy', $category))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Category::query()->count())->toBe(1);
});

it('refuses to delete a category that is in use', function () {
    $category = Category::factory()->create();
    Transaction::factory()->create(['category_id' => $category->id]);

    $this->from(route('categories.index'))
        ->delete(route('categories.destroy', $category))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Category::query()->count())->toBe(1);
});

it('keeps guests out', function () {
    auth()->logout();

    $this->get(route('categories.index'))->assertRedirect(route('login'));
});
