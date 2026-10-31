<?php

namespace Database\Seeders;

use App\Actions\Invoices\RecordInvoicePayment;
use App\Actions\Invoices\StoreInvoice;
use App\Actions\Quotations\ConvertQuotationToInvoice;
use App\Actions\Quotations\StoreQuotation;
use App\Enums\CategoryType;
use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\RecurringInterval;
use App\Enums\RecurringKind;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RecurringItem;
use App\Models\TimeEntry;
use App\Services\RecurringScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Clients, projects and a spread of documents to try phase 3 with: one
 * invoice in each state the UI can show, including a quotation that became
 * an invoice and an English-speaking client.
 *
 * Everything goes through the same actions the forms use, so the numbers,
 * statuses and fund movements are exactly what real use would produce.
 *
 *   php artisan db:seed --class=DemoDocumentSeeder
 */
class DemoDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $today = CarbonImmutable::today();
        $account = Account::query()->where('name', 'Maybank Bisnes')->first()
            ?? Account::query()->orderBy('id')->firstOrFail();

        $clients = $this->clients();
        $projects = $this->projects($clients);

        $store = app(StoreInvoice::class);
        $pay = app(RecordInvoicePayment::class);

        // A paid invoice, from a quotation the client accepted.
        $quotation = app(StoreQuotation::class)->handle([
            'client_id' => $clients['maju']->id,
            'project_id' => $projects['portal']->id,
            'issue_date' => $today->subMonths(3)->toDateString(),
            'valid_until' => $today->subMonths(3)->addDays(30)->toDateString(),
            'status' => QuotationStatus::Sent->value,
            'locale' => Locale::Malay->value,
            'notes' => 'Terima kasih atas peluang ini.',
            'terms' => 'Deposit 50% sebelum kerja bermula.',
            'items' => [
                ['description' => 'Reka bentuk UI/UX', 'quantity' => 1, 'unit_price' => 4_500_00],
                ['description' => 'Pembangunan portal', 'quantity' => 1, 'unit_price' => 12_000_00],
                ['description' => 'Latihan pengguna', 'quantity' => '2.5', 'unit_price' => 600_00],
            ],
        ]);

        $paid = app(ConvertQuotationToInvoice::class)->handle(
            $quotation,
            $today->subMonths(3)->addDays(5)->toDateString(),
        );
        $paid->update(['status' => InvoiceStatus::Sent, 'sent_at' => $paid->issue_date]);

        // Deposit, then the balance.
        $pay->handle($paid->refresh(), [
            'account_id' => $account->id,
            'amount' => intdiv($paid->total, 2),
            'date' => $today->subMonths(3)->addDays(9)->toDateString(),
        ]);
        $pay->handle($paid->refresh(), [
            'account_id' => $account->id,
            'amount' => $paid->refresh()->outstanding(),
            'date' => $today->subMonths(2)->toDateString(),
        ]);

        // A part paid invoice, still inside its terms.
        $partial = $store->handle([
            'client_id' => $clients['wei']->id,
            'project_id' => $projects['stok']->id,
            'issue_date' => $today->subDays(10)->toDateString(),
            'status' => InvoiceStatus::Sent->value,
            // This client reads English.
            'locale' => Locale::English->value,
            'terms' => '50% deposit, balance on delivery.',
            'items' => [
                ['description' => 'Inventory system — phase 1', 'quantity' => 1, 'unit_price' => 8_000_00],
            ],
        ]);
        $partial->update(['sent_at' => $partial->issue_date]);
        $pay->handle($partial->refresh(), [
            'account_id' => $account->id,
            'amount' => 4_000_00,
            'date' => $today->subDays(6)->toDateString(),
        ]);

        // An overdue invoice.
        $overdue = $store->handle([
            'client_id' => $clients['tani']->id,
            'issue_date' => $today->subMonths(2)->toDateString(),
            'due_date' => $today->subDays(20)->toDateString(),
            'status' => InvoiceStatus::Sent->value,
            'locale' => Locale::Malay->value,
            'items' => [
                ['description' => 'Penyelenggaraan tahunan', 'quantity' => 12, 'unit_price' => 250_00],
            ],
        ]);
        $overdue->update([
            'status' => InvoiceStatus::Overdue,
            'sent_at' => $overdue->issue_date,
        ]);

        // A draft still being written, and a quotation waiting for an answer.
        $store->handle([
            'client_id' => $clients['wei']->id,
            'issue_date' => $today->toDateString(),
            'locale' => Locale::English->value,
            'items' => [
                ['description' => 'Inventory system — phase 2', 'quantity' => 1, 'unit_price' => 6_500_00],
            ],
        ]);

        app(StoreQuotation::class)->handle([
            'client_id' => $clients['tani']->id,
            'project_id' => $projects['kedai']->id,
            'issue_date' => $today->subDays(5)->toDateString(),
            'valid_until' => $today->addDays(25)->toDateString(),
            'status' => QuotationStatus::Sent->value,
            'locale' => Locale::Malay->value,
            'items' => [
                ['description' => 'Laman web kedai', 'quantity' => 1, 'unit_price' => 5_500_00],
                ['description' => 'Hosting setahun', 'quantity' => 1, 'unit_price' => 480_00],
            ],
        ]);

        $this->recurringItems($clients, $projects, $account, $today);
        $this->timeEntries($projects, $today);

        $counts = [
            'clients' => Client::query()->count(),
            'projects' => Project::query()->count(),
            'invoices' => Invoice::query()->count(),
        ];

        $counts['recurring'] = RecurringItem::query()->count();
        $counts['hours'] = (int) round(((int) TimeEntry::query()->sum('minutes')) / 60);

        $this->command?->info(
            "DemoDocumentSeeder: {$counts['clients']} clients, "
            ."{$counts['projects']} projects, {$counts['invoices']} invoices, "
            ."{$counts['recurring']} recurring items, {$counts['hours']} hours logged."
        );
    }

    /**
     * A few subscriptions on both sides, including one close enough to be
     * drafted on the next run and one cost inside its reminder window.
     *
     * @param  array<string, Client>  $clients
     * @param  array<string, Project>  $projects
     */
    private function recurringItems(
        array $clients,
        array $projects,
        Account $account,
        CarbonImmutable $today,
    ): void {
        $operating = Category::query()
            ->where('type', CategoryType::Expense)
            ->whereJsonContains('name->en', 'Software & SaaS')
            ->value('id');
        $hosting = Category::query()
            ->where('type', CategoryType::Expense)
            ->whereJsonContains('name->en', 'Hosting & Server')
            ->value('id');

        $items = [
            // Due in 8 days, so the next run drafts the invoice.
            [
                'kind' => RecurringKind::ClientBilling,
                'name' => 'Hosting & domain tahunan',
                'amount' => 1_200_00,
                'interval' => RecurringInterval::Yearly,
                'next_date' => $today->addDays(8),
                'client_id' => $clients['maju']->id,
                'project_id' => $projects['portal']->id,
                'auto_generate' => true,
                'remind_days_before' => 14,
            ],
            // Raised by hand each month.
            [
                'kind' => RecurringKind::ClientBilling,
                'name' => 'Retainer penyelenggaraan',
                'amount' => 800_00,
                'interval' => RecurringInterval::Monthly,
                'next_date' => $today->addDays(20),
                'client_id' => $clients['wei']->id,
                'project_id' => $projects['stok']->id,
                'auto_generate' => false,
                'remind_days_before' => 7,
            ],
            // Inside its reminder window.
            [
                'kind' => RecurringKind::OwnCost,
                'name' => 'Langganan Figma',
                'amount' => 68_00,
                'interval' => RecurringInterval::Monthly,
                'next_date' => $today->addDays(5),
                'category_id' => $operating,
                'account_id' => $account->id,
                'remind_days_before' => 14,
            ],
            [
                'kind' => RecurringKind::OwnCost,
                'name' => 'Server VPS',
                'amount' => 120_00,
                'interval' => RecurringInterval::Monthly,
                // The last day of the month, so the anchor has work to do.
                'next_date' => $today->endOfMonth(),
                'category_id' => $hosting,
                'account_id' => $account->id,
                'remind_days_before' => 7,
            ],
            [
                'kind' => RecurringKind::OwnCost,
                'name' => 'Lesen IDE tahunan',
                'amount' => 950_00,
                'interval' => RecurringInterval::Yearly,
                'next_date' => $today->addMonths(4),
                'category_id' => $operating,
                'account_id' => $account->id,
                'remind_days_before' => 30,
            ],
        ];

        foreach ($items as $attributes) {
            /** @var CarbonImmutable $next */
            $next = $attributes['next_date'];

            RecurringItem::query()->firstOrCreate(
                ['name' => $attributes['name']],
                [
                    ...$attributes,
                    'next_date' => $next->toDateString(),
                    'anchor_day' => RecurringScheduleService::anchorFor($next),
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * A few weeks of logged work, so the project profit report has hours to
     * cost and one project comes out underwater.
     *
     * @param  array<string, Project>  $projects
     */
    private function timeEntries(array $projects, CarbonImmutable $today): void
    {
        if (TimeEntry::query()->exists()) {
            return;
        }

        // [project, days back, minutes, billable, note]
        $sessions = [
            ['portal', 95, 300, true, 'Reka bentuk wireframe'],
            ['portal', 92, 420, true, 'Reka bentuk UI'],
            ['portal', 80, 480, true, 'Pembangunan modul tempahan'],
            ['portal', 76, 390, true, 'Pembangunan modul bayaran'],
            ['portal', 60, 240, true, 'Pembetulan pepijat'],
            ['portal', 50, 180, false, 'Mesyuarat dalaman'],
            ['portal', 40, 300, true, 'Latihan pengguna'],
            // A job that is taking far longer than it was quoted for.
            ['stok', 25, 480, true, 'Analisa keperluan'],
            ['stok', 22, 480, true, 'Skema pangkalan data'],
            ['stok', 18, 480, true, 'Pembangunan modul stok'],
            ['stok', 15, 480, true, 'Pembangunan modul laporan'],
            ['stok', 11, 420, true, 'Integrasi pembekal'],
            ['stok', 8, 480, true, 'Pembetulan & ujian'],
            ['stok', 4, 300, true, 'Semakan dengan klien'],
            ['stok', 2, 120, false, 'Admin & e-mel'],
        ];

        foreach ($sessions as [$project, $daysAgo, $minutes, $billable, $note]) {
            $started = $today->subDays($daysAgo)->setTime(9, 30);

            TimeEntry::query()->create([
                'project_id' => $projects[$project]->id,
                'started_at' => $started,
                'ended_at' => $started->addMinutes($minutes),
                'minutes' => $minutes,
                'note' => $note,
                'billable' => $billable,
            ]);
        }
    }

    /**
     * @return array<string, Client>
     */
    private function clients(): array
    {
        return [
            'maju' => Client::query()->firstOrCreate(
                ['email' => 'ahmad@maju.test'],
                [
                    'name' => 'Ahmad bin Ali',
                    'company' => 'Syarikat Maju Sdn Bhd',
                    'phone' => '012-345 6789',
                    'address' => "No 12, Jalan Besar\n50000 Kuala Lumpur",
                    'reg_no' => '202301012345',
                    'preferred_locale' => Locale::Malay,
                ],
            ),
            // Reads English, so their invoices and e-mails are in English.
            'wei' => Client::query()->firstOrCreate(
                ['email' => 'lee@weitrading.test'],
                [
                    'name' => 'Lee Wei',
                    'company' => 'Wei Trading Sdn Bhd',
                    'phone' => '016-987 6543',
                    'address' => "88 Jalan Pasar\n10200 Georgetown, Penang",
                    'preferred_locale' => Locale::English,
                ],
            ),
            'tani' => Client::query()->firstOrCreate(
                ['email' => 'siti@tanimaju.test'],
                [
                    'name' => 'Siti Nurhaliza',
                    'company' => 'Tani Maju Enterprise',
                    'phone' => '019-222 3344',
                    'preferred_locale' => Locale::Malay,
                ],
            ),
        ];
    }

    /**
     * @param  array<string, Client>  $clients
     * @return array<string, Project>
     */
    private function projects(array $clients): array
    {
        $today = CarbonImmutable::today();

        return [
            'portal' => Project::query()->firstOrCreate(
                ['name' => 'Portal Tempahan Maju'],
                [
                    'client_id' => $clients['maju']->id,
                    'contract_amount' => 18_000_00,
                    'status' => ProjectStatus::Completed,
                    'start_date' => $today->subMonths(4)->toDateString(),
                    'end_date' => $today->subMonths(1)->toDateString(),
                    'internal_hourly_rate' => 80_00,
                ],
            ),
            'stok' => Project::query()->firstOrCreate(
                ['name' => 'Sistem Stok Wei Trading'],
                [
                    'client_id' => $clients['wei']->id,
                    'contract_amount' => 14_500_00,
                    'status' => ProjectStatus::Active,
                    'start_date' => $today->subMonths(1)->toDateString(),
                    'internal_hourly_rate' => 80_00,
                ],
            ),
            'kedai' => Project::query()->firstOrCreate(
                ['name' => 'Laman Web Kedai Tani'],
                [
                    'client_id' => $clients['tani']->id,
                    'contract_amount' => 5_980_00,
                    'status' => ProjectStatus::Prospect,
                    'internal_hourly_rate' => 80_00,
                ],
            ),
        ];
    }
}
