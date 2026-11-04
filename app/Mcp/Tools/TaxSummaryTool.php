<?php

namespace App\Mcp\Tools;

use App\Services\TaxSummaryService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('tax_summary')]
#[Description('Tax picture for a year: income, deductible and non-deductible expenses, taxable income, and what sits in the tax fund. An estimate for planning only, not an LHDN filing. Amounts are in ringgit.')]
#[IsReadOnly]
#[IsIdempotent]
class TaxSummaryTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()
                ->description('Calendar year, e.g. 2026. Defaults to the current year.'),
        ];
    }

    public function handle(Request $request, TaxSummaryService $tax): Response|ResponseFactory
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $report = $tax->report($validated['year'] ?? CarbonImmutable::today()->year);

        return Response::structured([
            'currency' => 'MYR',
            'year' => $report['year'],
            'income' => (float) Money::toRinggit($report['income']),
            'deductible_expenses' => (float) Money::toRinggit($report['deductible']),
            'non_deductible_expenses' => (float) Money::toRinggit($report['non_deductible']),
            'taxable_income' => (float) Money::toRinggit($report['taxable']),
            'tax_fund_balance' => (float) Money::toRinggit($report['tax_fund']),
            'disclaimer' => 'Estimate only. Confirm with LHDN or a tax agent before filing.',
        ]);
    }
}
