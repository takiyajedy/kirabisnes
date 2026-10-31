<?php

namespace App\Support;

use App\Services\ReportExportService;

/**
 * A report, described once, for both file formats (PLAN.md phase 5).
 *
 * The PDF and the CSV of the same report have to carry the same figures. The
 * way to make that true rather than hoped for is to describe the report once
 * — a title, a few headline figures, then tables — and let each format read
 * that description. Adding a column cannot then land in one file and not the
 * other.
 *
 * Amounts are held as sen and formatted by the format: the PDF wants
 * "RM 1,250.00" and the spreadsheet wants 1250.00, or the cell will not add
 * up.
 */
final class ReportSheet
{
    /** @var array<int, array{label: string, sen: ?int, value: ?string, negative: bool}> */
    private array $figures = [];

    /** @var array<int, array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, mixed>, total: bool}>}> */
    private array $tables = [];

    private ?string $note = null;

    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
    ) {}

    public static function make(string $title, string $subtitle): self
    {
        return new self($title, $subtitle);
    }

    /**
     * A headline figure. Pass sen for money, or a ready string for anything
     * else — a count, a percentage.
     */
    public function figure(string $label, ?int $sen = null, ?string $value = null): self
    {
        $this->figures[] = [
            'label' => $label,
            'sen' => $sen,
            'value' => $value,
            'negative' => $sen !== null && $sen < 0,
        ];

        return $this;
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function table(string $heading, array $columns): self
    {
        $this->tables[] = [
            'heading' => $heading,
            'columns' => $columns,
            'rows' => [],
        ];

        return $this;
    }

    /**
     * A row in the table most recently opened.
     *
     * A cell is either a plain string or `['sen' => int]`, which each format
     * then renders its own way.
     *
     * @param  array<int, mixed>  $cells
     */
    public function row(array $cells, bool $total = false): self
    {
        $last = count($this->tables) - 1;

        if ($last < 0) {
            return $this;
        }

        $this->tables[$last]['rows'][] = ['cells' => $cells, 'total' => $total];

        return $this;
    }

    public function note(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    /**
     * The shape the PDF template reads, with money formatted for reading.
     *
     * @return array<string, mixed>
     */
    public function forPdf(string $locale): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'note' => $this->note,
            'figures' => array_map(
                fn (array $figure) => [
                    'label' => $figure['label'],
                    'value' => $figure['sen'] === null
                        ? ($figure['value'] ?? '')
                        : Money::format($figure['sen'], $locale),
                    'negative' => $figure['negative'],
                ],
                $this->figures,
            ),
            'tables' => array_map(
                fn (array $table) => [
                    ...$table,
                    'rows' => array_map(
                        fn (array $row) => [
                            ...$row,
                            'cells' => array_map(
                                fn (mixed $cell) => is_array($cell)
                                    ? Money::format((int) $cell['sen'], $locale)
                                    : (string) $cell,
                                $row['cells'],
                            ),
                        ],
                        $table['rows'],
                    ),
                ],
                $this->tables,
            ),
        ];
    }

    /**
     * The same report as spreadsheet rows.
     *
     * Money goes in as a plain number so the cells add up, and each table
     * keeps its heading so a reader can tell the sections apart.
     *
     * @return array<int, array<int, string>>
     */
    public function forCsv(): array
    {
        $rows = [[$this->title], [$this->subtitle], []];

        if ($this->figures !== []) {
            foreach ($this->figures as $figure) {
                $rows[] = [
                    $figure['label'],
                    $figure['sen'] === null
                        ? ($figure['value'] ?? '')
                        : ReportExportService::amount($figure['sen']),
                ];
            }

            $rows[] = [];
        }

        foreach ($this->tables as $table) {
            $rows[] = [$table['heading']];
            $rows[] = $table['columns'];

            foreach ($table['rows'] as $row) {
                $rows[] = array_map(
                    fn (mixed $cell) => is_array($cell)
                        ? ReportExportService::amount((int) $cell['sen'])
                        : (string) $cell,
                    $row['cells'],
                );
            }

            $rows[] = [];
        }

        if ($this->note !== null) {
            $rows[] = [$this->note];
        }

        return $rows;
    }
}
