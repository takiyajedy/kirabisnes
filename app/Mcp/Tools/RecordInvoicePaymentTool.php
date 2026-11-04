<?php

namespace App\Mcp\Tools;

use App\Actions\Invoices\RecordInvoicePayment;
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
 * Routed through RecordInvoicePayment, which books the income, splits it
 * across the funds and re-derives the invoice status in one transaction.
 * A payment written any other way would leave the funds untouched and the
 * invoice stuck on "sent".
 */
#[Name('record_payment')]
#[Description('Record a payment received against an invoice. Amount is in ringgit. This books income, splits it across the funds and may mark the invoice paid. Call the reference data tool for the account id.')]
#[IsDestructive]
class RecordInvoicePaymentTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->description('Which invoice was paid.')->required(),

            'amount' => $schema->number()
                ->description('Amount received in ringgit. May be a part payment.')
                ->required(),

            'account_id' => $schema->integer()->description('Which account the money landed in.')->required(),

            'date' => $schema->string()->description('Date received (YYYY-MM-DD). Defaults to today.'),
            'reference' => $schema->string()->description('Bank reference, if any.'),
        ];
    }

    public function handle(Request $request, RecordInvoicePayment $record): Response|ResponseFactory
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|gt:0',
            'account_id' => 'required|integer|exists:accounts,id',
            'date' => 'nullable|date_format:Y-m-d',
            'reference' => 'nullable|string|max:100',
        ]);

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->findOrFail($validated['invoice_id']);

        $owedBefore = $invoice->outstanding();
        $amount = Money::toSen($validated['amount']);

        if ($amount > $owedBefore) {
            return Response::error(sprintf(
                'Not recorded: %s is more than the RM %s still owed on %s. Record the amount actually received.',
                'RM '.Money::toRinggit($amount),
                Money::toRinggit($owedBefore),
                $invoice->number,
            ));
        }

        $payment = $record->handle($invoice, [
            'account_id' => $validated['account_id'],
            'amount' => $amount,
            'date' => $validated['date'] ?? CarbonImmutable::today()->toDateString(),
            'reference' => $validated['reference'] ?? null,
        ]);

        $invoice = $invoice->fresh();

        return Response::structured([
            'recorded' => true,
            'transaction_id' => $payment->id,
            'invoice_number' => $invoice->number,
            'currency' => 'MYR',
            'amount' => (float) Money::toRinggit($amount),
            'still_owed' => (float) Money::toRinggit($invoice->outstanding()),
            'invoice_status' => $invoice->status->value,
        ]);
    }
}
