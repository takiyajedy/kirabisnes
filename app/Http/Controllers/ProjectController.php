<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\Projects\ProjectRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\TimeEntryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Client;
use App\Models\Project;
use App\Services\ProjectProfitService;
use App\Services\ProjectSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectSummaryService $summaries,
        private ProjectProfitService $profit,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        $status = $request->string('status')->value();
        $status = in_array($status, ProjectStatus::values(), true) ? $status : null;
        $clientId = $request->integer('client_id') ?: null;

        $projects = Project::query()
            ->with('client')
            ->when($search, fn (Builder $q, string $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($clientId, fn (Builder $q, int $id) => $q->where('client_id', $id))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        $this->summaries->attach($projects->getCollection());

        return Inertia::render('Projects/Index', [
            'projects' => ProjectResource::collection($projects)->response()->getData(true),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'client_id' => $clientId,
            ],
            'statuses' => ProjectStatus::options(),
            'clients' => ClientResource::collection(Client::query()->ordered()->get())->resolve(),
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load('client');
        $project->summary = $this->summaries->for($project);

        return Inertia::render('Projects/Show', [
            'project' => ProjectResource::make($project)->resolve(),
            // With the logged time costed in (PLAN.md phase 5).
            'profit' => $this->profit->for($project),
            'timeEntries' => TimeEntryResource::collection(
                $project->timeEntries()->ordered()->get(),
            )->resolve(),
            'client' => ClientResource::make($project->client)->resolve(),
            'invoices' => InvoiceResource::collection(
                $project->invoices()->ordered()->get(),
            )->resolve(),
            'quotations' => QuotationResource::collection(
                $project->quotations()->ordered()->get(),
            )->resolve(),
            // Everything tagged to this project, which is what the gross
            // profit figure is built from.
            'transactions' => TransactionResource::collection(
                $project->transactions()
                    ->with(['account', 'category', 'fund'])
                    ->orderByDesc('date')->orderByDesc('id')
                    ->get(),
            )->resolve(),
            'statuses' => ProjectStatus::options(),
            'clients' => ClientResource::collection(Client::query()->ordered()->get())->resolve(),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        Project::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('project.created'),
        ]);

        return back();
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('project.updated'),
        ]);

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        $hasRecords = $project->invoices()->exists()
            || $project->quotations()->exists()
            || $project->transactions()->exists();

        if ($hasRecords) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('project.cannot_delete'),
            ]);

            return back();
        }

        $project->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('project.deleted'),
        ]);

        return to_route('projects.index');
    }
}
