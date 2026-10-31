<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\MarkInvoiceSent;
use App\Actions\Invoices\RecordInvoicePayment;
use App\Actions\Invoices\RefreshInvoiceState;
use App\Actions\Invoices\StoreInvoice;
use App\Actions\Invoices\UpdateInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Transactions\DeleteTransaction;
use App\Enums\CategoryType;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Http\Requests\Invoices\InvoicePaymentRequest;
use App\Http\Requests\Invoices\InvoiceRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ProjectResource;
use App\Mail\InvoiceMail;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\DocumentNumberService;
use App\Services\DocumentPdfService;
use App\Support\BusinessProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $invoices = Invoice::query()
            ->with(['client', 'project'])
            ->when($filters['status'], fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($filters['client_id'], fn (Builder $q, int $id) => $q->where('client_id', $id))
            ->when($filters['outstanding'], fn (Builder $q) => $q->outstanding())
            ->when($filters['search'], fn (Builder $q, string $term) => $q
                ->where(fn (Builder $inner) => $inner
                    ->where('number', 'like', "%{$term}%")
                    ->orWhereHas('client', fn (Builder $c) => $c
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%"))))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Invoices/Index', [
            'invoices' => InvoiceResource::collection($invoices)->response()->getData(true),
            'filters' => $filters,
            'statuses' => InvoiceStatus::options(),
            'clients' => ClientResource::collection(Client::query()->ordered()->get())->resolve(),
            'totals' => [
                'outstanding' => (int) Invoice::query()->outstanding()
                    ->selectRaw('COALESCE(SUM(total - paid_amount), 0) AS owed')
                    ->value('owed'),
            ],
        ]);
    }

    public function create(Request $request, DocumentNumberService $numbers): Response
    {
        return Inertia::render('Invoices/Form', [
            // The client and project pages link here with these filled in.
            'preset' => [
                'client_id' => $request->integer('client_id') ?: null,
                'project_id' => $request->integer('project_id') ?: null,
            ],
            'invoice' => null,
            'nextNumber' => $numbers->preview(DocumentType::Invoice),
            ...$this->formOptions(),
        ]);
    }

    public function show(Invoice $invoice, DocumentPdfService $pdfs): Response
    {
        $invoice->load(['client', 'project', 'items', 'quotation', 'payments.account']);

        return Inertia::render('Invoices/Show', [
            'invoice' => InvoiceResource::make($invoice)->resolve(),
            'client' => ClientResource::make($invoice->client)->resolve(),
            'accounts' => AccountResource::collection(
                Account::query()->active()->ordered()->get(),
            )->resolve(),
            'incomeCategories' => CategoryResource::collection(
                Category::query()->where('type', CategoryType::Income)->ordered()->get(),
            )->resolve(),
            'pdfName' => $pdfs->fileName($invoice, $invoice->client),
            'canEmail' => $invoice->client->email !== null,
            // The link to hand a client directly, when e-mail is not the way.
            'publicLink' => $invoice->status === InvoiceStatus::Draft
                ? null
                : PublicInvoiceController::linkFor($invoice),
        ]);
    }

    public function edit(Invoice $invoice): Response
    {
        abort_unless($invoice->status->isEditable(), 403);

        $invoice->load(['client', 'project', 'items']);

        return Inertia::render('Invoices/Form', [
            'preset' => ['client_id' => null, 'project_id' => null],
            'invoice' => InvoiceResource::make($invoice)->resolve(),
            'nextNumber' => $invoice->number,
            ...$this->formOptions(),
        ]);
    }

    public function store(InvoiceRequest $request, StoreInvoice $store): RedirectResponse
    {
        $invoice = $store->handle($request->toAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('invoice.created', ['number' => $invoice->number]),
        ]);

        return to_route('invoices.show', $invoice);
    }

    public function update(
        InvoiceRequest $request,
        Invoice $invoice,
        UpdateInvoice $update,
    ): RedirectResponse {
        abort_unless($invoice->status->isEditable(), 403);

        $update->handle($invoice, $request->toAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('invoice.updated'),
        ]);

        return to_route('invoices.show', $invoice);
    }

    /**
     * Record money arriving against this invoice.
     */
    public function pay(
        InvoicePaymentRequest $request,
        Invoice $invoice,
        RecordInvoicePayment $record,
    ): RedirectResponse {
        abort_if($invoice->status === InvoiceStatus::Void, 403);

        $record->handle($invoice, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('invoice.payment_recorded'),
        ]);

        return back();
    }

    /**
     * Undo a payment that was recorded by mistake.
     */
    public function removePayment(
        Invoice $invoice,
        Transaction $payment,
        DeleteTransaction $delete,
        RefreshInvoiceState $refresh,
    ): RedirectResponse {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $delete->handle($payment);
        $refresh->handle($invoice->refresh());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('invoice.payment_removed'),
        ]);

        return back();
    }

    public function void(Invoice $invoice, VoidInvoice $void): RedirectResponse
    {
        $void->handle($invoice);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('invoice.voided'),
        ]);

        return back();
    }

    /**
     * Send the invoice to the client, in the client's own language.
     */
    public function send(Invoice $invoice, MarkInvoiceSent $markSent): RedirectResponse
    {
        $invoice->load(['client', 'items']);

        if ($invoice->client->email === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('invoice.error.no_client_email'),
            ]);

            return back();
        }

        abort_if($invoice->status === InvoiceStatus::Void, 403);

        Mail::to($invoice->client->email)
            // The client reads their language, not the owner's.
            ->locale($invoice->locale->value)
            ->send(new InvoiceMail($invoice));

        $markSent->handle($invoice);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('invoice.sent', ['email' => $invoice->client->email]),
        ]);

        return back();
    }

    /**
     * Mark it sent without e-mailing, for an invoice handed over by hand.
     */
    public function markSent(Invoice $invoice, MarkInvoiceSent $markSent): RedirectResponse
    {
        abort_if($invoice->status === InvoiceStatus::Void, 403);

        $markSent->handle($invoice);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('invoice.marked_sent'),
        ]);

        return back();
    }

    public function pdf(
        Request $request,
        Invoice $invoice,
        DocumentPdfService $pdfs,
    ): HttpResponse {
        $pdf = $pdfs->forInvoice($invoice);
        $name = $pdfs->fileName($invoice, $invoice->client);

        return $request->boolean('download')
            ? $pdf->download($name)
            : $pdf->stream($name);
    }

    /**
     * Only a draft can be deleted; anything a client has seen is voided.
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === InvoiceStatus::Draft, 403);

        $invoice->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('invoice.deleted'),
        ]);

        return to_route('invoices.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $business = BusinessProfile::all();

        return [
            'clients' => ClientResource::collection(Client::query()->ordered()->get())->resolve(),
            'projects' => ProjectResource::collection(
                Project::query()->with('client')->ordered()->get(),
            )->resolve(),
            'statuses' => InvoiceStatus::options(),
            'locales' => Locale::options(),
            'defaults' => [
                'locale' => $business['default_document_locale'],
                'payment_terms_days' => (int) $business['default_payment_terms_days'],
            ],
        ];
    }

    /**
     * @return array{status: ?string, client_id: ?int, search: ?string, outstanding: bool}
     */
    private function filters(Request $request): array
    {
        $status = $request->string('status')->value();

        return [
            'status' => in_array($status, InvoiceStatus::values(), true) ? $status : null,
            'client_id' => $request->integer('client_id') ?: null,
            'search' => $request->string('search')->trim()->value() ?: null,
            'outstanding' => $request->boolean('outstanding'),
        ];
    }
}
