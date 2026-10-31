<?php

use App\Actions\Transactions\StoreTransaction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Testing\TestResponse;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The acceptance criterion for phase 5: the 2026 reports download as a PDF
 * and a CSV, in Malay and in English, with the same figures as the screen.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);
    Setting::put('business_name', 'KiraBisnes Sdn Bhd');

    // The owner reads Malay throughout; the export language is its own choice.
    $this->actingAs(User::factory()->create(['locale' => 'ms']));
    app()->setLocale('ms');

    $this->account = Account::factory()->create(['opening_balance' => 1_000_00]);

    $hosting = Category::query()->whereJsonContains('name->en', 'Hosting & Server')->firstOrFail();
    $web = Category::query()->whereJsonContains('name->en', 'Web Project')->firstOrFail();

    exportRecord(TransactionType::Income, 50_000_00, '2026-03-01', ['category_id' => $web->id]);
    exportRecord(TransactionType::Expense, 1_200_00, '2026-04-01', [
        'category_id' => $hosting->id, 'tax_deductible' => true,
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function exportRecord(TransactionType $type, int $sen, string $date, array $attributes = []): void
{
    app(StoreTransaction::class)->handle([
        'type' => $type->value,
        'account_id' => test()->account->id,
        'amount' => $sen,
        'date' => $date,
        'description' => 'Test',
        ...$attributes,
    ]);
}

/** The text layer of a PDF, lowercased and with the spacing normalised. */
function reportPdfText(string $binary): string
{
    $text = (new Parser)->parseContent($binary)->getText();

    return mb_strtolower((string) preg_replace('/\s+/u', ' ', $text));
}

/**
 * The bytes of a download.
 *
 * A CSV is streamed and a PDF is not, so the two are read differently.
 */
function downloadBody(TestResponse $response): string
{
    return $response->baseResponse instanceof StreamedResponse
        ? $response->streamedContent()
        : (string) $response->getContent();
}

function exportUrl(string $report, string $format, string $locale, array $extra = []): string
{
    return route('reports.export', [
        'report' => $report,
        'format' => $format,
        'locale' => $locale,
        'period' => 'this_year',
        ...$extra,
    ]);
}

it('downloads the profit and loss as a Malay PDF', function () {
    $response = $this->get(exportUrl('profit-loss', 'pdf', 'ms'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    $text = reportPdfText(downloadBody($response));

    expect($text)->toContain('untung rugi')
        ->toContain('duit masuk')
        ->toContain('untung bersih')
        ->toContain('rm 50,000.00')
        ->toContain('kirabisnes sdn bhd');
});

it('downloads the same report as an English PDF while the app is in Malay', function () {
    $response = $this->get(exportUrl('profit-loss', 'pdf', 'en'));

    $text = reportPdfText(downloadBody($response));

    expect($text)->toContain('profit and loss')
        ->toContain('money in')
        ->toContain('net profit')
        // And none of the Malay labels leaked in.
        ->not->toContain('untung rugi')
        ->not->toContain('duit masuk');
});

it('leaves the app language alone after an export', function () {
    $this->get(exportUrl('profit-loss', 'pdf', 'en'));

    expect(app()->getLocale())->toBe('ms');
});

it('downloads a CSV with headers in the chosen language', function () {
    $malay = downloadBody($this->get(exportUrl('profit-loss', 'csv', 'ms')));
    $english = downloadBody($this->get(exportUrl('profit-loss', 'csv', 'en')));

    expect($malay)->toContain('Untung Rugi')
        ->toContain('Duit Masuk')
        ->and($english)->toContain('Profit and Loss')
        ->toContain('Money in')
        ->not->toContain('Duit Masuk');
});

it('writes amounts a spreadsheet can add up', function () {
    $csv = downloadBody($this->get(exportUrl('profit-loss', 'csv', 'ms')));

    // Plain numbers, not "RM 50,000.00", or the cell arrives as text.
    expect($csv)->toContain('50000.00')
        ->toContain('1200.00')
        ->not->toContain('RM 50,000.00');
});

it('starts the CSV with a byte order mark so Excel reads the accents', function () {
    $csv = downloadBody($this->get(exportUrl('profit-loss', 'csv', 'ms')));

    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF");
});

it('carries the same figures into the PDF and the CSV', function () {
    // The two formats are built from one description, so they cannot drift.
    $pdf = reportPdfText(downloadBody($this->get(exportUrl('profit-loss', 'pdf', 'en'))));
    $csv = downloadBody($this->get(exportUrl('profit-loss', 'csv', 'en')));

    expect($pdf)->toContain('rm 50,000.00')
        ->toContain('rm 48,800.00')
        ->and($csv)->toContain('50000.00')
        ->toContain('48800.00');
});

it('matches the figures the page itself shows', function () {
    $page = $this->get(route('reports.profit-loss', ['period' => 'this_year']));

    $page->assertOk()->assertInertia(fn ($p) => $p
        ->component('Reports/ProfitLoss')
        ->where('report.income', 50_000_00)
        ->where('report.expense', 1_200_00)
        ->where('report.net', 48_800_00),
    );

    $csv = downloadBody($this->get(exportUrl('profit-loss', 'csv', 'ms')));

    expect($csv)->toContain('50000.00')->toContain('48800.00');
});

it('names the file after the report and its window', function () {
    $this->get(exportUrl('profit-loss', 'csv', 'ms'))
        ->assertDownload('profit-loss-2026-01-01-2026-12-31.csv');

    $this->get(exportUrl('tax', 'pdf', 'ms', ['year' => 2026]))
        ->assertDownload('tax-2026.pdf');
});

it('exports every report in both formats', function () {
    foreach (['profit-loss', 'cash-flow', 'tax', 'projects', 'forecast'] as $report) {
        foreach (['csv', 'pdf'] as $format) {
            $response = $this->get(exportUrl($report, $format, 'en'));

            $response->assertOk();

            expect(downloadBody($response))->not->toBe('');
        }
    }
});

it('puts the estimate warning on the tax report, in both languages', function () {
    // The plan asks for this wording in so many words.
    $malay = reportPdfText(downloadBody($this->get(exportUrl('tax', 'pdf', 'ms'))));
    $english = reportPdfText(downloadBody($this->get(exportUrl('tax', 'pdf', 'en'))));

    expect($malay)->toContain('anggaran sahaja')
        ->toContain('lhdn')
        ->and($english)->toContain('estimate only')
        ->toContain('lhdn');
});

it('falls back to the profit and loss for an unknown report name', function () {
    $csv = downloadBody($this->get(exportUrl('something-else', 'csv', 'en')));

    expect($csv)->toContain('Profit and Loss');
});

it('falls back to the reading language when none is asked for', function () {
    $csv = downloadBody($this->get(route('reports.export', [
        'report' => 'profit-loss',
        'format' => 'csv',
        'period' => 'this_year',
    ])));

    expect($csv)->toContain('Untung Rugi');
});

it('keeps guests out of the exports', function () {
    auth()->logout();

    $this->get(exportUrl('profit-loss', 'csv', 'ms'))->assertRedirect(route('login'));
});
