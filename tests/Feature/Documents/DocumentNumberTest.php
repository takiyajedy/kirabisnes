<?php

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Models\Setting;
use App\Services\DocumentNumberService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-30 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->numbers = app(DocumentNumberService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('starts each document kind at one', function () {
    expect($this->numbers->next(DocumentType::Invoice))->toBe('INV-2026-0001')
        ->and($this->numbers->next(DocumentType::Quotation))->toBe('QUO-2026-0001');
});

it('counts up within a kind', function () {
    $this->numbers->next(DocumentType::Invoice);
    $this->numbers->next(DocumentType::Invoice);

    expect($this->numbers->next(DocumentType::Invoice))->toBe('INV-2026-0003');
});

it('keeps a separate counter per kind', function () {
    $this->numbers->next(DocumentType::Invoice);
    $this->numbers->next(DocumentType::Invoice);

    // Quotations are not pushed along by invoices.
    expect($this->numbers->next(DocumentType::Quotation))->toBe('QUO-2026-0001');
});

it('starts again at one in a new year', function () {
    $this->numbers->next(DocumentType::Invoice);

    CarbonImmutable::setTestNow('2027-01-02 09:00:00');

    expect($this->numbers->next(DocumentType::Invoice))->toBe('INV-2027-0001');
});

it('pads the number to four digits and keeps going past that', function () {
    DocumentSequence::query()->create([
        'type' => DocumentType::Invoice->value,
        'year' => 2026,
        'last_number' => 9_999,
    ]);

    expect($this->numbers->next(DocumentType::Invoice))->toBe('INV-2026-10000');
});

it('follows the prefix set in the business profile', function () {
    Setting::put('invoice_prefix', 'BIL');

    expect($this->numbers->next(DocumentType::Invoice))->toBe('BIL-2026-0001');
});

it('shows the next number without taking it', function () {
    expect($this->numbers->preview(DocumentType::Invoice))->toBe('INV-2026-0001')
        ->and($this->numbers->preview(DocumentType::Invoice))->toBe('INV-2026-0001')
        ->and(DocumentSequence::query()->count())->toBe(0);
});

it('never hands the same number out twice', function () {
    // The real protection is the row lock, which a single-process test cannot
    // exercise; what it can prove is that a long run of numbers has no
    // repeats and no gaps, which is what the lock is there to guarantee.
    $taken = collect(range(1, 50))
        ->map(fn () => $this->numbers->next(DocumentType::Invoice));

    expect($taken->unique())->toHaveCount(50)
        ->and($taken->last())->toBe('INV-2026-0050');
});

it('takes a number when the year counter already exists', function () {
    // The counter is created on the first document of the year. Taking a
    // number afterwards must not try to create it again: an unconditional
    // insert would hit the unique key on (type, year) and throw.
    DocumentSequence::query()->create([
        'type' => DocumentType::Invoice->value,
        'year' => 2026,
        'last_number' => 7,
    ]);

    expect($this->numbers->next(DocumentType::Invoice))->toBe('INV-2026-0008')
        ->and(DocumentSequence::query()->count())->toBe(1);
});

it('does not touch the counter row when it is only reading it', function () {
    $this->numbers->next(DocumentType::Invoice);

    DB::enableQueryLog();
    $this->numbers->next(DocumentType::Invoice);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    // Once the counter exists there is no insert at all, which is what keeps
    // concurrent requests from locking each other out.
    expect($queries->contains(fn (string $q) => str_contains($q, 'insert')))->toBeFalse();
});

it('locks the counter row while taking a number', function () {
    // SQLite locks the whole database file, so its grammar drops the clause
    // and there is nothing to find in the log. The app runs on MySQL, where
    // the row lock is what keeps two requests from sharing a number.
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Row locks need MySQL; SQLite locks the whole file.');
    }

    DB::enableQueryLog();
    $this->numbers->next(DocumentType::Invoice);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($queries->contains(fn (string $q) => str_contains($q, 'for update')))->toBeTrue();
});
