<?php

namespace App\Mcp\Tools;

use App\Services\ProfitLossService;
use App\Support\Money;
use App\Support\Period;
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

#[Name('profit_loss')]
#[Description('Profit and loss for a period: money in, money out, net profit, and the breakdown per category. Amounts are in ringgit.')]
#[IsReadOnly]
#[IsIdempotent]
class ProfitLossTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(['this_month', 'last_month', 'this_year', 'custom'])
                ->description('Which window to report on.')
                ->default('this_month'),

            'from' => $schema->string()
                ->description('Start date (YYYY-MM-DD). Only used when period is "custom".'),

            'to' => $schema->string()
                ->description('End date (YYYY-MM-DD). Only used when period is "custom".'),
        ];
    }

    public function handle(Request $request, ProfitLossService $profitLoss): Response|ResponseFactory
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:this_month,last_month,this_year,custom',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);

        $period = Period::resolve(
            $validated['period'] ?? 'this_month',
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        $report = $profitLoss->report($period);

        $rows = fn (array $list) => array_map(
            fn (array $row) => ['category' => $row['name'], 'amount' => (float) Money::toRinggit($row['amount'])],
            $list,
        );

        return Response::structured([
            'currency' => 'MYR',
            'from' => $period->fromDate(),
            'to' => $period->toDate(),
            'income' => (float) Money::toRinggit($report['income']),
            'expense' => (float) Money::toRinggit($report['expense']),
            'net_profit' => (float) Money::toRinggit($report['net']),
            'income_by_category' => $rows($report['income_rows']),
            'expense_by_category' => $rows($report['expense_rows']),
        ]);
    }
}
