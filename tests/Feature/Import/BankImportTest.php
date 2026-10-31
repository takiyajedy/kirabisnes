<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\BankImportService;
use App\Support\BankStatement;
use Carbon\CarbonImmutable;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->import = app(BankImportService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** Write a CSV to a temporary file and read it back as a statement. */
function statementFrom(string $csv, bool $hasHeaders = true): BankStatement
{
    $path = tempnam(sys_get_temp_dir(), 'stmt').'.csv';
    file_put_contents($path, $csv);

    try {
        return BankStatement::fromFile($path, $hasHeaders);
    } finally {
        @unlink($path);
    }
}

/** The default mapping for the three column fixture. */
function threeColumnMapping(): array
{
    return ['date' => 0, 'description' => 1, 'amount' => 2];
}

// --- reading the file ----------------------------------------------------

it('reads a comma separated statement', function () {
    $statement = statementFrom(
        "Date,Description,Amount\n15/10/2026,Bayaran klien,3000.00\n16/10/2026,Hosting,-120.50"
    );

    expect($statement->headers)->toBe(['Date', 'Description', 'Amount'])
        ->and($statement->rows)->toHaveCount(2)
        ->and($statement->rows[0][1])->toBe('Bayaran klien');
});

it('reads a semicolon separated statement', function () {
    $statement = statementFrom("Date;Description;Amount\n15/10/2026;Bayaran;3000.00");

    expect($statement->headers)->toHaveCount(3)
        ->and($statement->rows[0][2])->toBe('3000.00');
});

it('strips the byte order mark Excel adds', function () {
    $statement = statementFrom("\xEF\xBB\xBFDate,Description,Amount\n15/10/2026,Kopi,-8.00");

    // Without this the first header arrives with junk in front of it.
    expect($statement->headers[0])->toBe('Date');
});

it('names the columns by position when there is no header row', function () {
    $statement = statementFrom('15/10/2026,Kopi,-8.00', hasHeaders: false);

    expect($statement->headers)->toBe(['Column 1', 'Column 2', 'Column 3'])
        ->and($statement->rows)->toHaveCount(1);
});

it('ignores blank lines', function () {
    $statement = statementFrom("Date,Description,Amount\n\n15/10/2026,Kopi,-8.00\n\n");

    expect($statement->rows)->toHaveCount(1);
});

// --- reading an amount ---------------------------------------------------

it('reads amounts in the shapes banks write them', function (string $given, int $expected) {
    expect(BankImportService::parseAmount($given))->toBe($expected);
})->with([
    ['3000.00', 300_000],
    ['3,000.00', 300_000],
    ['RM 3,000.00', 300_000],
    ['-120.50', -12_050],
    // Brackets mean negative on a statement, and so does a trailing DR.
    ['(120.50)', -12_050],
    ['120.50 DR', -12_050],
    ['120.50 CR', 12_050],
    ['', 0],
    ['-', 0],
]);

// --- reading a date ------------------------------------------------------

it('reads dates in the shapes banks write them', function (string $given, ?string $expected) {
    expect(BankImportService::parseDate($given))->toBe($expected);
})->with([
    ['2026-10-15', '2026-10-15'],
    ['15/10/2026', '2026-10-15'],
    ['15-10-2026', '2026-10-15'],
    ['15 Oct 2026', '2026-10-15'],
    ['20261015', '2026-10-15'],
    ['tarikh', null],
    ['', null],
]);

it('reads an ambiguous date as day first', function () {
    // 03/04/2026 is 3 April here, not 4 March. Guessing the other way would
    // silently misdate a whole statement.
    expect(BankImportService::parseDate('03/04/2026'))->toBe('2026-04-03')
        // Unless the owner says the file is month first.
        ->and(BankImportService::parseDate('03/04/2026', 'mdy'))->toBe('2026-03-04');
});

// --- the preview ---------------------------------------------------------

it('calls a row new when nothing on the books matches it', function () {
    $statement = statementFrom("Date,Description,Amount\n10/10/2026,Bayaran klien,3000.00");

    $preview = $this->import->preview($statement, threeColumnMapping(), $this->account);

    expect($preview)->toHaveCount(1)
        ->and($preview[0]['status'])->toBe('new')
        ->and($preview[0]['amount'])->toBe(300_000)
        ->and($preview[0]['type'])->toBe('income')
        ->and($preview[0]['date'])->toBe('2026-10-10');
});

it('calls money out an expense', function () {
    $statement = statementFrom("Date,Description,Amount\n10/10/2026,Hosting,-120.50");

    $preview = $this->import->preview($statement, threeColumnMapping(), $this->account);

    expect($preview[0]['type'])->toBe('expense')
        ->and($preview[0]['amount'])->toBe(12_050);
});

it('matches a row against a transaction a day or two out', function () {
    // The day a payment was made and the day the bank posted it are rarely
    // the same.
    $existing = Transaction::factory()->ofType(TransactionType::Income)
        ->amount(300_000)
        ->create(['account_id' => $this->account->id, 'date' => '2026-10-08']);

    $statement = statementFrom("Date,Description,Amount\n10/10/2026,Bayaran klien,3000.00");

    $preview = $this->import->preview($statement, threeColumnMapping(), $this->account);

    expect($preview[0]['status'])->toBe('matched')
        ->and($preview[0]['matched_id'])->toBe($existing->id);
});

it('does not match beyond the three day window', function () {
    Transaction::factory()->ofType(TransactionType::Income)->amount(300_000)
        ->create(['account_id' => $this->account->id, 'date' => '2026-10-01']);

    $statement = statementFrom("Date,Description,Amount\n10/10/2026,Bayaran klien,3000.00");

    expect($this->import->preview($statement, threeColumnMapping(), $this->account)[0]['status'])
        ->toBe('new');
});

it('does not match a different amount', function () {
    Transaction::factory()->ofType(TransactionType::Income)->amount(299_900)
        ->create(['account_id' => $this->account->id, 'date' => '2026-10-10']);

    $statement = statementFrom("Date,Description,Amount\n10/10/2026,Bayaran klien,3000.00");

    expect($this->import->preview($statement, threeColumnMapping(), $this->account)[0]['status'])
        ->toBe('new');
});

it('does not match a transaction on another account', function () {
    $other = Account::factory()->create();
    Transaction::factory()->ofType(TransactionType::Income)->amount(300_000)
        ->create(['account_id' => $other->id, 'date' => '2026-10-10']);

    $statement = statementFrom("Date,Description,Amount\n10/10/2026,Bayaran klien,3000.00");

    expect($this->import->preview($statement, threeColumnMapping(), $this->account)[0]['status'])
        ->toBe('new');
});

it('claims each transaction once, so two identical charges match two rows', function () {
    Transaction::factory()->ofType(TransactionType::Expense)->amount(5_000)
        ->create(['account_id' => $this->account->id, 'date' => '2026-10-10']);

    $statement = statementFrom(
        "Date,Description,Amount\n10/10/2026,Parking,-50.00\n11/10/2026,Parking,-50.00"
    );

    $preview = $this->import->preview($statement, threeColumnMapping(), $this->account);

    // One is already on the books; the other is genuinely missing.
    expect($preview[0]['status'])->toBe('matched')
        ->and($preview[1]['status'])->toBe('new');
});

it('skips a row it cannot read, and says why', function () {
    $statement = statementFrom(
        "Date,Description,Amount\ntarikh rosak,Bayaran,3000.00\n10/10/2026,,3000.00\n10/10/2026,Bayaran,"
    );

    $preview = $this->import->preview($statement, threeColumnMapping(), $this->account);

    expect(array_column($preview, 'status'))->toBe(['skipped', 'skipped', 'skipped'])
        ->and($preview[0]['error'])->toBe(__('import.error.bad_date'))
        ->and($preview[1]['error'])->toBe(__('import.error.no_description'))
        ->and($preview[2]['error'])->toBe(__('import.error.no_amount'));
});

it('reads a statement with separate debit and credit columns', function () {
    $statement = statementFrom(
        "Date,Description,Debit,Credit\n10/10/2026,Bayaran klien,,3000.00\n11/10/2026,Hosting,120.50,"
    );

    $preview = $this->import->preview($statement, [
        'date' => 0, 'description' => 1, 'amount' => 2, 'credit' => 3,
    ], $this->account);

    expect($preview[0]['type'])->toBe('income')
        ->and($preview[0]['amount'])->toBe(300_000)
        ->and($preview[1]['type'])->toBe('expense')
        ->and($preview[1]['amount'])->toBe(12_050);
});

// --- importing -----------------------------------------------------------

it('imports only the rows the owner picked', function () {
    $statement = statementFrom(
        "Date,Description,Amount\n10/10/2026,Satu,-10.00\n11/10/2026,Dua,-20.00\n12/10/2026,Tiga,-30.00"
    );

    $preview = $this->import->preview($statement, threeColumnMapping(), $this->account);

    $result = $this->import->import($preview, [1, 3], $this->account);

    expect($result['imported'])->toBe(2)
        ->and($result['skipped'])->toBe(1)
        ->and(Transaction::query()->orderBy('id')->pluck('description')->all())
        ->toBe(['Satu', 'Tiga']);
});

it('will not import a row that already matched', function () {
    Transaction::factory()->ofType(TransactionType::Expense)->amount(1_000)
        ->create(['account_id' => $this->account->id, 'date' => '2026-10-10']);

    $statement = statementFrom("Date,Description,Amount\n10/10/2026,Satu,-10.00");
    $preview = $this->import->preview($statement, threeColumnMapping(), $this->account);

    // Even if the row number is handed in, a matched row is already recorded.
    $result = $this->import->import($preview, [1], $this->account);

    expect($result['imported'])->toBe(0)
        ->and(Transaction::query()->count())->toBe(1);
});

it('files an imported row against the account it came from', function () {
    $statement = statementFrom("Date,Description,Amount\n10/10/2026,Bayaran,3000.00");
    $preview = $this->import->preview($statement, threeColumnMapping(), $this->account);

    $this->import->import($preview, [1], $this->account);

    $transaction = Transaction::query()->firstOrFail();

    expect($transaction->account_id)->toBe($this->account->id)
        ->and($transaction->type)->toBe(TransactionType::Income)
        ->and($transaction->amount)->toBe(300_000)
        ->and($transaction->reference)->toBe(__('import.reference'));
});
