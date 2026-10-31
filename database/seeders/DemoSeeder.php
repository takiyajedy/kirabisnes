<?php

namespace Database\Seeders;

use App\Actions\Transactions\StoreTransaction;
use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Fund;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A year and a bit of plausible trading, for trying the app out and for
 * measuring how the dashboard holds up (PLAN.md phase 2 asks for roughly a
 * thousand transactions).
 *
 * Every row goes through StoreTransaction, the same path the forms use, so
 * the fund movements it leaves behind are exactly what real use would
 * produce — a seeder that wrote them itself could drift from the real rules.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /** Months of history to generate, counting back from this month. */
    private const MONTHS = 14;

    public function run(): void
    {
        $store = app(StoreTransaction::class);

        $accounts = $this->accounts();
        $incomeCategories = Category::query()->where('type', CategoryType::Income)->get();
        $expenseCategories = Category::query()->where('type', CategoryType::Expense)->get();
        $ownerPay = Fund::query()->where('slug', 'owner_pay')->firstOrFail();
        $capital = Fund::query()->where('slug', 'capital')->firstOrFail();

        if ($incomeCategories->isEmpty() || $expenseCategories->isEmpty()) {
            $this->command?->warn('Run the base seeders first; DemoSeeder needs the default categories.');

            return;
        }

        $created = 0;
        $firstMonth = CarbonImmutable::today()->startOfMonth()->subMonths(self::MONTHS - 1);

        // The demo rows are read by a Malay-first owner, so the month names
        // inside them are written in Malay whatever the console locale is.
        $callerLocale = CarbonImmutable::getLocale();
        CarbonImmutable::setLocale('ms');

        // One transaction per commit would mean a thousand commits, so the
        // whole run shares a single one.
        DB::transaction(function () use (
            $store, $accounts, $incomeCategories, $expenseCategories,
            $ownerPay, $capital, $firstMonth, &$created,
        ): void {
            for ($i = 0; $i < self::MONTHS; $i++) {
                $month = $firstMonth->addMonths($i);

                if ($i === 0) {
                    // The owner puts in the starting money.
                    $store->handle([
                        'type' => TransactionType::CapitalInjection->value,
                        'account_id' => $accounts['bank']->id,
                        'amount' => 10_000_00,
                        'date' => $month->toDateString(),
                        'description' => 'Modal permulaan',
                        'fund_id' => $capital->id,
                    ]);
                    $created++;
                }

                foreach ($this->incomes($month, $accounts, $incomeCategories) as $attributes) {
                    $store->handle($attributes);
                    $created++;
                }

                foreach ($this->expenses($month, $accounts, $expenseCategories) as $attributes) {
                    $store->handle($attributes);
                    $created++;
                }

                // Pay yourself once a month, late in the month.
                $store->handle([
                    'type' => TransactionType::OwnerDraw->value,
                    'account_id' => $accounts['bank']->id,
                    'amount' => fake()->numberBetween(1_500_00, 3_500_00),
                    'date' => $month->day(min(25, $month->daysInMonth))->toDateString(),
                    'description' => 'Gaji sendiri '.$month->translatedFormat('F Y'),
                    'fund_id' => $ownerPay->id,
                ]);
                $created++;

                // Move some cash between own accounts now and then.
                if ($i % 3 === 0) {
                    $store->handle([
                        'type' => TransactionType::Transfer->value,
                        'account_id' => $accounts['bank']->id,
                        'to_account_id' => $accounts['ewallet']->id,
                        'amount' => fake()->numberBetween(100_00, 500_00),
                        'date' => $month->day(10)->toDateString(),
                        'description' => 'Topup e-wallet',
                    ]);
                    $created++;
                }
            }
        });

        CarbonImmutable::setLocale($callerLocale);

        $this->command?->info("DemoSeeder: {$created} transactions over ".self::MONTHS.' months.');
    }

    /**
     * @param  array<string, Account>  $accounts
     * @param  Collection<int, Category>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function incomes(CarbonImmutable $month, array $accounts, Collection $categories): array
    {
        $rows = [];

        foreach (range(1, fake()->numberBetween(4, 8)) as $ignored) {
            $category = $categories->random();

            $rows[] = [
                'type' => TransactionType::Income->value,
                'account_id' => $accounts['bank']->id,
                'amount' => fake()->numberBetween(800_00, 8_000_00),
                'date' => $this->dayIn($month),
                'description' => $category->getTranslation('name', 'ms').' — '.fake()->company(),
                'reference' => 'INV-'.$month->format('ym').'-'.fake()->numberBetween(100, 999),
                'category_id' => $category->id,
            ];
        }

        return $rows;
    }

    /**
     * How often each kind of expense turns up, relative to the others.
     *
     * Picking categories uniformly would give every one the same slice of the
     * donut, which is not what a real month looks like and would make the
     * chart useless to look at. Keyed by the English name so reordering the
     * seeder does not shuffle the weights.
     */
    private const EXPENSE_WEIGHTS = [
        'Fuel / Toll / Parking' => 30,
        'Software & SaaS' => 14,
        'Hosting & Server' => 10,
        'Bank & Gateway Fees' => 10,
        'Internet & Phone' => 8,
        'Ads & Marketing' => 6,
        'Others' => 6,
        'Subcontract' => 4,
        'Courses & Books' => 4,
        'Hardware' => 3,
        'Domain' => 2,
    ];

    /**
     * @param  array<string, Account>  $accounts
     * @param  Collection<int, Category>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function expenses(CarbonImmutable $month, array $accounts, Collection $categories): array
    {
        $rows = [];
        $bag = $this->weightedCategories($categories);
        $names = ['bank', 'ewallet', 'cash'];

        foreach (range(1, fake()->numberBetween(62, 72)) as $ignored) {
            $category = fake()->randomElement($bag);
            $account = $accounts[fake()->randomElement($names)];

            $rows[] = [
                'type' => TransactionType::Expense->value,
                'account_id' => $account->id,
                'amount' => fake()->numberBetween(5_00, 250_00),
                'date' => $this->dayIn($month),
                'description' => $category->getTranslation('name', 'ms'),
                'category_id' => $category->id,
                'tax_deductible' => $category->tax_deductible_default,
            ];
        }

        return $rows;
    }

    /**
     * The categories repeated according to their weight, so randomElement()
     * picks the common ones more often.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<int, Category>
     */
    private function weightedCategories(Collection $categories): array
    {
        $bag = [];

        foreach ($categories as $category) {
            $weight = self::EXPENSE_WEIGHTS[$category->getTranslation('name', 'en')] ?? 5;

            for ($i = 0; $i < $weight; $i++) {
                $bag[] = $category;
            }
        }

        return $bag;
    }

    /**
     * @return array<string, Account>
     */
    private function accounts(): array
    {
        return [
            'bank' => Account::query()->firstOrCreate(
                ['name' => 'Maybank Bisnes'],
                [
                    'type' => AccountType::Bank,
                    'bank_name' => 'Maybank',
                    'opening_balance' => 0,
                    'is_active' => true,
                    'sort_order' => 1,
                ],
            ),
            'ewallet' => Account::query()->firstOrCreate(
                ['name' => 'Touch n Go eWallet'],
                [
                    'type' => AccountType::EWallet,
                    'opening_balance' => 0,
                    'is_active' => true,
                    'sort_order' => 2,
                ],
            ),
            'cash' => Account::query()->firstOrCreate(
                ['name' => 'Tunai'],
                [
                    'type' => AccountType::Cash,
                    'opening_balance' => 0,
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            ),
        ];
    }

    /**
     * A day inside the month, never past today.
     */
    private function dayIn(CarbonImmutable $month): string
    {
        $today = CarbonImmutable::today();
        $last = $month->isSameMonth($today) ? $today->day : $month->daysInMonth;

        return $month->day(fake()->numberBetween(1, max(1, $last)))->toDateString();
    }
}
