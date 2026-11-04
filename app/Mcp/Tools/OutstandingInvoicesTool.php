<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
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

#[Name('outstanding_invoices')]
#[Description('Invoices with money still owed: who owes it, how much, when it was due, and whether it is overdue. Amounts are in ringgit.')]
#[IsReadOnly]
#[IsIdempotent]
class OutstandingInvoicesTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'overdue_only' => $schema->boolean()
                ->description('Return only invoices already past their due date.')
                ->default(false),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $overdueOnly = (bool) $request->get('overdue_only', false);

        $owedSen = 0;

        $invoices = Invoice::query()
            ->with('client')
            ->outstanding()
            ->ordered()
            ->get()
            ->when($overdueOnly, fn ($list) => $list->filter(fn (Invoice $i) => $i->isPastDue()))
            ->map(function (Invoice $invoice) use (&$owedSen): array {
                $owedSen += $invoice->outstanding();

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'client' => $invoice->client?->displayName(),
                    'status' => $invoice->status->value,
                    'issue_date' => $invoice->issue_date->toDateString(),
                    'due_date' => $invoice->due_date->toDateString(),
                    'overdue' => $invoice->isPastDue(),
                    'total' => (float) Money::toRinggit($invoice->total),
                    'paid' => (float) Money::toRinggit($invoice->paid_amount),
                    'owed' => (float) Money::toRinggit($invoice->outstanding()),
                ];
            })
            ->values()
            ->all();

        return Response::structured([
            'currency' => 'MYR',
            'count' => count($invoices),
            'total_owed' => (float) Money::toRinggit($owedSen),
            'invoices' => $invoices,
        ]);
    }
}
