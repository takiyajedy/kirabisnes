<?php

namespace App\Mcp\Tools;

use App\Models\Transaction;
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

#[Name('recent_transactions')]
#[Description('Recent transactions, newest first, optionally filtered by type or date range. Amounts are in ringgit.')]
#[IsReadOnly]
#[IsIdempotent]
class RecentTransactionsTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['income', 'expense', 'transfer', 'owner_draw', 'capital_injection'])
                ->description('Only this kind of transaction.'),

            'from' => $schema->string()->description('Earliest date (YYYY-MM-DD).'),
            'to' => $schema->string()->description('Latest date (YYYY-MM-DD).'),

            'limit' => $schema->integer()
                ->description('How many to return, 1-100.')
                ->default(25),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'type' => 'nullable|string|in:income,expense,transfer,owner_draw,capital_injection',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $rows = Transaction::query()
            ->with(['account', 'category', 'fund'])
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($validated['limit'] ?? 25)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'date' => $t->date->toDateString(),
                'type' => $t->type->value,
                'description' => $t->description,
                'amount' => (float) Money::toRinggit($t->amount),
                'account' => $t->account?->name,
                'category' => $t->category?->name,
                'fund' => $t->fund?->name,
                'reference' => $t->reference,
            ])
            ->all();

        return Response::structured(['currency' => 'MYR', 'count' => count($rows), 'transactions' => $rows]);
    }
}
