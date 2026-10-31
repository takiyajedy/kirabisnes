<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\QuotationResource;
use App\Models\Client;
use App\Services\ClientSummaryService;
use App\Services\ProjectSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(private ClientSummaryService $summaries) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;

        $clients = Client::query()
            ->withCount('projects')
            ->when($search, fn (Builder $q, string $term) => $q
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        $this->summaries->attach($clients->getCollection());

        return Inertia::render('Clients/Index', [
            'clients' => ClientResource::collection($clients)->response()->getData(true),
            'filters' => ['search' => $search],
            'locales' => Locale::options(),
        ]);
    }

    public function show(Client $client, ProjectSummaryService $projectSummaries): Response
    {
        $client->summary = $this->summaries->for($client);

        $projects = $client->projects()->with('client')->ordered()->get();
        $projectSummaries->attach($projects);

        return Inertia::render('Clients/Show', [
            'client' => ClientResource::make($client)->resolve(),
            'projects' => ProjectResource::collection($projects)->resolve(),
            'invoices' => InvoiceResource::collection(
                $client->invoices()->with('project')->ordered()->limit(20)->get(),
            )->resolve(),
            'quotations' => QuotationResource::collection(
                $client->quotations()->with('project')->ordered()->limit(20)->get(),
            )->resolve(),
            'locales' => Locale::options(),
        ]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('client.created'),
        ]);

        return back();
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('client.updated'),
        ]);

        return back();
    }

    /**
     * A client with documents against them is kept: deleting would leave
     * invoices and projects pointing at nothing, and the foreign keys forbid
     * it anyway.
     */
    public function destroy(Client $client): RedirectResponse
    {
        $hasRecords = $client->projects()->exists()
            || $client->invoices()->exists()
            || $client->quotations()->exists();

        if ($hasRecords) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('client.cannot_delete'),
            ]);

            return back();
        }

        $client->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('client.deleted'),
        ]);

        return to_route('clients.index');
    }
}
