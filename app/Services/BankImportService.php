<?php

namespace App\Services;

use App\Actions\Transactions\StoreTransaction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Support\BankStatement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Matches a bank statement against what is already recorded, so an import
 * adds the rows that are missing and leaves the rest alone (PLAN.md phase 6).
 *
 * A row counts as already recorded when an existing transaction on the same
 * account has the same amount within three days of it. That window is there
 * because the date a payment was made and the date the bank posted it are
 * rarely the same day, and matching on the exact date would duplicate
 * nearly everything.
 *
 * Each existing transaction can only be claimed once, so two identical
 * charges in the same week match two transactions, not the same one twice.
 */
class BankImportService
{
    /** How far apart the two dates may be and still be the same payment. */
    public const MATCH_DAYS = 3;

    public function __construct(private StoreTransaction $store) {}

    /**
     * Read the statement and say what each row would do.
     *
     * @param  array{date: int, description: int, amount: int, credit?: ?int}  $mapping
     * @return array<int, array{
     *     row: int, date: ?string, description: string, amount: int,
     *     type: string, status: string, matched_id: ?int, error: ?string,
     * }>
     */
    public function preview(
        BankStatement $statement,
        array $mapping,
        Account $account,
        string $dateFormat = 'auto',
    ): array {
        $existing = $this->candidates($account);
        $claimed = [];
        $preview = [];

        foreach ($statement->rows as $index => $row) {
            $parsed = $this->parseRow($row, $mapping, $dateFormat);

            if ($parsed['error'] !== null) {
                $preview[] = [...$parsed, 'row' => $index + 1, 'status' => 'skipped', 'matched_id' => null];

                continue;
            }

            $match = $this->findMatch($existing, $parsed, $claimed);

            if ($match !== null) {
                $claimed[$match] = true;
            }

            $preview[] = [
                ...$parsed,
                'row' => $index + 1,
                'status' => $match === null ? 'new' : 'matched',
                'matched_id' => $match,
            ];
        }

        return $preview;
    }

    /**
     * Write the rows the owner chose.
     *
     * Only rows the preview called new can be imported: a row that matched
     * something is already on the books, and a row that could not be read
     * has nothing to write.
     *
     * @param  array<int, array<string, mixed>>  $rows  Preview rows.
     * @param  array<int, int>  $accept  Row numbers to import.
     * @return array{imported: int, skipped: int}
     */
    public function import(array $rows, array $accept, Account $account): array
    {
        $wanted = array_flip($accept);
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $isWanted = isset($wanted[$row['row']]) && $row['status'] === 'new';

            if (! $isWanted) {
                $skipped++;

                continue;
            }

            // Through the same action the forms use: an imported income is
            // income, and has to reach the funds the same way as any other.
            $this->store->handle([
                'type' => $row['type'],
                'account_id' => $account->id,
                'amount' => $row['amount'],
                'date' => $row['date'],
                'description' => $row['description'],
                'reference' => self::message('import.reference'),
                'tax_deductible' => false,
            ]);

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Read one statement row.
     *
     * @param  array<int, string>  $row
     * @param  array{date: int, description: int, amount: int, credit?: ?int}  $mapping
     * @return array{date: ?string, description: string, amount: int, type: string, error: ?string}
     */
    public function parseRow(array $row, array $mapping, string $dateFormat = 'auto'): array
    {
        $date = self::parseDate($row[$mapping['date']] ?? '', $dateFormat);
        $description = trim($row[$mapping['description']] ?? '');

        $primary = self::parseAmount($row[$mapping['amount']] ?? '');

        // Two shapes of statement: one signed amount column, or separate
        // debit and credit columns.
        $creditColumn = $mapping['credit'] ?? null;

        if ($creditColumn === null) {
            $signed = $primary;
        } else {
            $credit = self::parseAmount($row[$creditColumn] ?? '');

            // With the columns apart, the amount column is the debit side,
            // so anything in it leaves the account whatever sign it carries.
            $signed = $credit !== 0 ? $credit : -abs($primary);
        }

        $error = match (true) {
            $date === null => self::message('import.error.bad_date'),
            $signed === 0 => self::message('import.error.no_amount'),
            $description === '' => self::message('import.error.no_description'),
            default => null,
        };

        return [
            'date' => $date,
            'description' => $description,
            'amount' => abs($signed),
            // Money in is income; money out is an expense. The owner can
            // refile either afterwards on the transactions page.
            'type' => $signed > 0
                ? TransactionType::Income->value
                : TransactionType::Expense->value,
            'error' => $error,
        ];
    }

    /**
     * An amount as integer sen, whatever the bank wrapped it in.
     *
     * Brackets mean negative on a statement; so does a trailing "DR".
     */
    public static function parseAmount(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $negative = str_contains($value, '(')
            || preg_match('/\bDR\b/i', $value) === 1
            || str_starts_with($value, '-');

        $cleaned = preg_replace('/[^0-9.]/', '', $value) ?? '';

        if ($cleaned === '' || $cleaned === '.') {
            return 0;
        }

        $sen = (int) round(((float) $cleaned) * 100);

        return $negative ? -$sen : $sen;
    }

    /**
     * A date as an ISO string, or null if it cannot be read.
     *
     * Day-first is tried before month-first: 03/04/2026 is 3 April here, not
     * 4 March, and guessing the other way round would silently misdate a
     * whole statement.
     */
    public static function parseDate(string $value, string $format = 'auto'): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $formats = match ($format) {
            'dmy' => ['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y'],
            'mdy' => ['m/d/Y', 'm-d-Y', 'm/d/y', 'm-d-y'],
            'ymd' => ['Y-m-d', 'Y/m/d', 'Ymd'],
            default => [
                'Y-m-d', 'Y/m/d',
                'd/m/Y', 'd-m-Y', 'd.m.Y',
                'd/m/y', 'd-m-y',
                'd M Y', 'd M y', 'd F Y',
                'Ymd',
            ],
        };

        foreach ($formats as $candidate) {
            // Carbon throws on a format that does not fit rather than
            // returning false, and trying several formats in turn is the
            // whole point here.
            try {
                $date = CarbonImmutable::createFromFormat($candidate, $value);
            } catch (\Throwable) {
                continue;
            }

            // The round trip is what makes this strict: Carbon will happily
            // read "15/10/2026" as a 'd/m/y' date and leave the rest as
            // trailing data, which would silently land in the wrong year.
            if ($date->format($candidate) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function dateFormats(): array
    {
        return array_map(
            fn (string $value) => [
                'value' => $value,
                'label' => self::message('import.date_format.'.$value),
            ],
            ['auto', 'dmy', 'mdy', 'ymd'],
        );
    }

    /**
     * A translated message.
     *
     * The translator's own return type allows an array, because a key can
     * resolve to one. These keys are sentences, so the narrowing happens
     * once here rather than at each call.
     */
    private static function message(string $key): string
    {
        return (string) __($key);
    }

    /**
     * The transactions a statement row could be matched against.
     *
     * @return Collection<int, Transaction>
     */
    private function candidates(Account $account): Collection
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->get(['id', 'amount', 'date', 'type']);
    }

    /**
     * @param  Collection<int, Transaction>  $existing
     * @param  array<string, mixed>  $parsed
     * @param  array<int, bool>  $claimed
     */
    private function findMatch(Collection $existing, array $parsed, array $claimed): ?int
    {
        $date = CarbonImmutable::parse($parsed['date']);

        foreach ($existing as $transaction) {
            if (isset($claimed[$transaction->id])) {
                continue;
            }

            if ($transaction->amount !== $parsed['amount']) {
                continue;
            }

            if (abs($transaction->date->diffInDays($date)) > self::MATCH_DAYS) {
                continue;
            }

            return $transaction->id;
        }

        return null;
    }
}
