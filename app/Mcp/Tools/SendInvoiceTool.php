<?php

namespace App\Mcp\Tools;

use App\Actions\Invoices\MarkInvoiceSent;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Mail;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

/**
 * The only tool here that reaches outside the business. Everything else
 * writes to a database the owner can correct; this one puts a demand for
 * money in someone else's inbox, and no undo exists for that.
 *
 * Hence the explicit confirm flag: a model that merely drifts onto this
 * tool will not have set it.
 */
#[Name('send_invoice')]
#[Description('Email an invoice to the client and mark it as sent. THIS SENDS A REAL EMAIL TO A REAL CLIENT and cannot be undone. Requires confirm=true. Always show the owner the invoice total and recipient and get their agreement before calling this.')]
#[IsDestructive]
#[IsOpenWorld]
class SendInvoiceTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->description('Which invoice to send.')->required(),

            'confirm' => $schema->boolean()
                ->description('Must be true. Set it only after the owner has explicitly agreed to send this invoice to this client.')
                ->required(),
        ];
    }

    public function handle(Request $request, MarkInvoiceSent $markSent): Response|ResponseFactory
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'confirm' => 'required|boolean',
        ]);

        if (! $validated['confirm']) {
            return Response::error('Not sent: confirm was false. Ask the owner to approve sending this invoice first.');
        }

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->with('client')->findOrFail($validated['invoice_id']);

        $email = $invoice->client?->email;

        if ($email === null || $email === '') {
            return Response::error("Not sent: client \"{$invoice->client?->displayName()}\" has no email address on file.");
        }

        Mail::to($email)
            // The client reads their language, not the owner's.
            ->locale($invoice->locale->value)
            ->send(new InvoiceMail($invoice));

        $markSent->handle($invoice);

        return Response::structured([
            'sent' => true,
            'invoice_id' => $invoice->id,
            'number' => $invoice->number,
            'to' => $email,
            'total' => (float) Money::toRinggit($invoice->total),
            'currency' => 'MYR',
            'status' => $invoice->fresh()->status->value,
        ]);
    }
}
