<?php

namespace App\Mcp\Tools;

use App\Actions\Invoices\StoreInvoice;
use App\Enums\Locale;
use App\Models\Invoice;
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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Creates the invoice as a draft, always. Nothing reaches the client until
 * send_invoice is called, which gives the owner a chance to read it first —
 * an invoice is a demand for money, and a wrong one is embarrassing to
 * retract.
 */
#[Name('create_invoice')]
#[Description('Create a draft invoice for a client. Line amounts are in ringgit. The invoice is NOT sent — it is saved as a draft, and send_invoice emails it. Call the reference data tool first for client and project ids.')]
#[IsDestructive]
class CreateInvoiceTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('Who the invoice is for.')->required(),

            'items' => $schema->array()
                ->description('Line items. Each is an object: {"description": string, "quantity": number, "unit_price": number in ringgit}.')
                ->required(),

            'issue_date' => $schema->string()->description('Issue date (YYYY-MM-DD). Defaults to today.'),
            'due_date' => $schema->string()->description('Due date (YYYY-MM-DD). Defaults to the business payment terms.'),
            'project_id' => $schema->integer()->description('Project this invoice belongs to, if any.'),
            'discount' => $schema->number()->description('Discount in ringgit off the subtotal.')->default(0),
            'notes' => $schema->string()->description('Note printed on the invoice.'),
            'locale' => $schema->string()->enum(['ms', 'en'])->description('Language the invoice is written in.'),
        ];
    }

    public function handle(Request $request, StoreInvoice $store): Response|ResponseFactory
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'issue_date' => 'nullable|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d',
            'project_id' => 'nullable|integer|exists:projects,id',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'locale' => 'nullable|string|in:ms,en',
        ]);

        $issueDate = $validated['issue_date'] ?? CarbonImmutable::today()->toDateString();

        $invoice = $store->handle([
            'client_id' => $validated['client_id'],
            'project_id' => $validated['project_id'] ?? null,
            'issue_date' => $issueDate,
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'locale' => $validated['locale'] ?? Locale::Malay->value,
            'discount' => Money::toSen($validated['discount'] ?? 0),
            'items' => array_map(fn (array $item) => [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => Money::toSen($item['unit_price']),
            ], $validated['items']),
        ]);

        return Response::structured(self::summarise($invoice->fresh(['items', 'client'])));
    }

    /**
     * @return array<string, mixed>
     */
    public static function summarise(Invoice $invoice): array
    {
        return [
            'created' => true,
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'client' => $invoice->client?->displayName(),
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'currency' => 'MYR',
            'subtotal' => (float) Money::toRinggit($invoice->subtotal),
            'discount' => (float) Money::toRinggit($invoice->discount),
            'total' => (float) Money::toRinggit($invoice->total),
            'next_step' => 'Draft only — nothing has been sent. Use send_invoice to email it to the client.',
        ];
    }
}
