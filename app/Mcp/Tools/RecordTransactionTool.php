<?php

namespace App\Mcp\Tools;

use App\Actions\Transactions\StoreTransaction;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Routed through StoreTransaction, not Transaction::create, so an income
 * recorded here is split across the funds exactly as one typed into the
 * app would be. Writing the row directly would leave the funds behind.
 */
#[Name('record_transaction')]
#[Description('Record a transaction in the books. Amounts are in ringgit (12.50 means RM 12.50). Call the reference data tool first to get account and category ids. This writes to real accounting records.')]
#[IsDestructive]
class RecordTransactionTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['income', 'expense', 'owner_draw', 'capital_injection'])
                ->description('What kind of movement this is.')
                ->required(),

            'amount' => $schema->number()
                ->description('Amount in ringgit, e.g. 1250.00 for RM 1,250.00. Must be positive.')
                ->required(),

            'date' => $schema->string()
                ->description('Date of the transaction (YYYY-MM-DD).')
                ->required(),

            'description' => $schema->string()
                ->description('What this was for, in the owner\'s own words.')
                ->required(),

            'account_id' => $schema->integer()
                ->description('Which account the money moved through.')
                ->required(),

            'category_id' => $schema->integer()
                ->description('Category id. Strongly recommended — it drives the reports and the tax split.'),

            'reference' => $schema->string()->description('Invoice or receipt reference, if any.'),

            'tax_deductible' => $schema->boolean()
                ->description('Override the category default for whether this expense is deductible.'),
        ];
    }

    public function handle(Request $request, StoreTransaction $store): Response|ResponseFactory
    {
        $validated = $request->validate([
            'type' => 'required|string|in:income,expense,owner_draw,capital_injection',
            'amount' => 'required|numeric|gt:0',
            'date' => 'required|date_format:Y-m-d',
            'description' => 'required|string|max:255',
            'account_id' => 'required|integer|exists:accounts,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'reference' => 'nullable|string|max:100',
            'tax_deductible' => 'nullable|boolean',
        ]);

        $transaction = $store->handle([
            ...$validated,
            // Ringgit on the wire, sen in the database — the same boundary
            // every FormRequest in this app applies.
            'amount' => Money::toSen($validated['amount']),
        ]);

        $transaction->load(['account', 'category', 'fund', 'fundMovements.fund']);

        return Response::structured([
            'recorded' => true,
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'date' => $transaction->date->toDateString(),
            'description' => $transaction->description,
            'amount' => (float) Money::toRinggit($transaction->amount),
            'account' => $transaction->account?->name,
            'category' => $transaction->category?->name,
            // Income is split across every fund rather than landing in one,
            // so the movements say more than a single fund name would.
            'fund' => $transaction->fund?->name,
            'fund_split' => $transaction->fundMovements
                ->map(fn ($movement) => [
                    'fund' => $movement->fund?->name,
                    'amount' => (float) Money::toRinggit((int) $movement->amount),
                ])
                ->values()
                ->all(),
        ]);
    }
}
