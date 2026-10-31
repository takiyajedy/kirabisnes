<?php

namespace App\Http\Controllers;

use App\Actions\Quotations\ConvertQuotationToInvoice;
use App\Actions\Quotations\StoreQuotation;
use App\Actions\Quotations\UpdateQuotation;
use App\Enums\DocumentType;
use App\Enums\Locale;
use App\Enums\QuotationStatus;
use App\Http\Requests\Quotations\QuotationRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\QuotationResource;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\DocumentNumberService;
use App\Services\DocumentPdfService;
use App\Support\BusinessProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $quotations = Quotation::query()
            ->with(['client', 'project'])
            ->when($filters['status'], fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($filters['client_id'], fn (Builder $q, int $id) => $q->where('client_id', $id))
            ->when($filters['search'], fn (Builder $q, string $term) => $q
                ->where(fn (Builder $inner) => $inner
                    ->where('number', 'like', "%{$term}%")
                    ->orWhereHas('client', fn (Builder $c) => $c
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%"))))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Quotations/Index', [
            'quotations' => QuotationResource::collection($quotations)->response()->getData(true),
            'filters' => $filters,
            'statuses' => QuotationStatus::options(),
            'clients' => ClientResource::collection(Client::query()->ordered()->get())->resolve(),
        ]);
    }

    public function create(Request $request, DocumentNumberService $numbers): Response
    {
        return Inertia::render('Quotations/Form', [
            // The client and project pages link here with these filled in.
            'preset' => [
                'client_id' => $request->integer('client_id') ?: null,
                'project_id' => $request->integer('project_id') ?: null,
            ],
            'quotation' => null,
            'nextNumber' => $numbers->preview(DocumentType::Quotation),
            ...$this->formOptions(),
        ]);
    }

    public function show(Quotation $quotation, DocumentPdfService $pdfs): Response
    {
        $quotation->load(['client', 'project', 'items', 'convertedInvoice']);

        return Inertia::render('Quotations/Show', [
            'quotation' => QuotationResource::make($quotation)->resolve(),
            'client' => ClientResource::make($quotation->client)->resolve(),
            'statuses' => QuotationStatus::options(),
            'pdfName' => $pdfs->fileName($quotation, $quotation->client),
        ]);
    }

    public function edit(Quotation $quotation): Response
    {
        abort_unless($quotation->status->isEditable() && ! $quotation->isConverted(), 403);

        $quotation->load(['client', 'project', 'items']);

        return Inertia::render('Quotations/Form', [
            'preset' => ['client_id' => null, 'project_id' => null],
            'quotation' => QuotationResource::make($quotation)->resolve(),
            'nextNumber' => $quotation->number,
            ...$this->formOptions(),
        ]);
    }

    public function store(QuotationRequest $request, StoreQuotation $store): RedirectResponse
    {
        $quotation = $store->handle($request->toAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('quotation.created', ['number' => $quotation->number]),
        ]);

        return to_route('quotations.show', $quotation);
    }

    public function update(
        QuotationRequest $request,
        Quotation $quotation,
        UpdateQuotation $update,
    ): RedirectResponse {
        abort_unless($quotation->status->isEditable() && ! $quotation->isConverted(), 403);

        $update->handle($quotation, $request->toAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('quotation.updated'),
        ]);

        return to_route('quotations.show', $quotation);
    }

    /**
     * Move a quotation along without opening the whole form.
     */
    public function status(Request $request, Quotation $quotation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', QuotationStatus::values())],
        ]);

        // A converted quotation is tied to an invoice; its status is settled.
        abort_if($quotation->isConverted(), 403);

        $quotation->update(['status' => $validated['status']]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('quotation.status_updated'),
        ]);

        return back();
    }

    public function convert(
        Quotation $quotation,
        ConvertQuotationToInvoice $convert,
    ): RedirectResponse {
        $invoice = $convert->handle($quotation);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('quotation.converted', ['number' => $invoice->number]),
        ]);

        return to_route('invoices.show', $invoice);
    }

    public function pdf(
        Request $request,
        Quotation $quotation,
        DocumentPdfService $pdfs,
    ): HttpResponse {
        $pdf = $pdfs->forQuotation($quotation);
        $name = $pdfs->fileName($quotation, $quotation->client);

        // Preview opens it in the browser; download saves it.
        return $request->boolean('download')
            ? $pdf->download($name)
            : $pdf->stream($name);
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        // A quotation that became an invoice is the record of what was agreed.
        abort_if($quotation->isConverted(), 403);

        $quotation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('quotation.deleted'),
        ]);

        return to_route('quotations.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'clients' => ClientResource::collection(Client::query()->ordered()->get())->resolve(),
            'projects' => ProjectResource::collection(
                Project::query()->with('client')->ordered()->get(),
            )->resolve(),
            'statuses' => QuotationStatus::options(),
            'locales' => Locale::options(),
            'defaults' => [
                'locale' => BusinessProfile::all()['default_document_locale'],
            ],
        ];
    }

    /**
     * @return array{status: ?string, client_id: ?int, search: ?string}
     */
    private function filters(Request $request): array
    {
        $status = $request->string('status')->value();

        return [
            'status' => in_array($status, QuotationStatus::values(), true) ? $status : null,
            'client_id' => $request->integer('client_id') ?: null,
            'search' => $request->string('search')->trim()->value() ?: null,
        ];
    }
}
