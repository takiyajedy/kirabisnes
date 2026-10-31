<?php

namespace App\Support;

/**
 * A bank statement CSV, read into rows (PLAN.md phase 6).
 *
 * Banks do not agree on anything: the column order differs, some put debit
 * and credit in separate columns, the date format varies, and amounts come
 * with thousands separators and sometimes brackets for a negative. So the
 * file is read as plainly as possible and the owner says which column is
 * which; nothing is guessed that cannot be checked on screen first.
 */
final class BankStatement
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    public function __construct(
        public readonly array $headers,
        public readonly array $rows,
    ) {}

    /**
     * Read an uploaded file.
     *
     * The delimiter is sniffed rather than assumed: a Malaysian bank export
     * is as likely to be semicolon separated as comma separated.
     */
    public static function fromFile(string $path, bool $hasHeaders = true): self
    {
        $contents = (string) file_get_contents($path);

        // Strip a byte order mark, which Excel adds and fgetcsv does not know
        // about: without this the first header arrives with junk in front.
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $delimiter = self::sniffDelimiter($contents);

        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line, $delimiter);
            $rows[] = array_map(fn (?string $cell) => trim((string) $cell), $row);
        }

        if ($rows === []) {
            return new self([], []);
        }

        if (! $hasHeaders) {
            // Without a header row the columns are named by position, so the
            // picker still has something to show.
            $width = count($rows[0]);

            return new self(
                array_map(fn (int $i) => 'Column '.($i + 1), range(0, $width - 1)),
                $rows,
            );
        }

        // The empty case returned above, so there is a header row to take.
        $headers = array_shift($rows);

        return new self($headers, $rows);
    }

    /**
     * The columns the owner can choose from, as value/label pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function columns(): array
    {
        return array_map(
            fn (int $index) => [
                'value' => (string) $index,
                'label' => $this->headers[$index] !== ''
                    ? $this->headers[$index]
                    : 'Column '.($index + 1),
            ],
            array_keys($this->headers),
        );
    }

    /**
     * The first few rows, so the owner can see what they are mapping.
     *
     * @return array<int, array<int, string>>
     */
    public function sample(int $limit = 5): array
    {
        return array_slice($this->rows, 0, $limit);
    }

    /**
     * Which single-character delimiter splits the file into the most even
     * rows. A comma that only appears inside quoted amounts would otherwise
     * win on count alone.
     */
    private static function sniffDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n");

        if ($firstLine === false) {
            return ',';
        }

        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = count(str_getcsv($firstLine, $candidate));

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
