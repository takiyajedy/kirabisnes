<?php

namespace App\Mcp\Tools;

use App\Models\Fund;
use App\Services\FundBalanceService;
use App\Support\Money;
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

#[Name('fund_balances')]
#[Description('Current balance of every fund the business splits its income across — operating, owner pay, tax, emergency and capital. Amounts are in ringgit.')]
#[IsReadOnly]
#[IsIdempotent]
class FundBalancesTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request, FundBalanceService $balances): Response|ResponseFactory
    {
        $all = $balances->all();

        $funds = Fund::query()->orderBy('sort_order')->get()
            ->map(fn (Fund $fund) => [
                'slug' => $fund->slug,
                'name' => $fund->name,
                'share_percent' => $fund->percentage,
                'balance' => (float) Money::toRinggit($all[$fund->id] ?? 0),
            ])
            ->all();

        return Response::structured([
            'currency' => 'MYR',
            'total' => (float) Money::toRinggit($balances->total()),
            'funds' => $funds,
        ]);
    }
}
