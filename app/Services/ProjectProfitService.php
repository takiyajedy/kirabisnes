<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

/**
 * Project profit with your own time costed in (PLAN.md phase 5).
 *
 *   profit = money received − costs tagged to the project − labour
 *   labour = logged minutes × the project's internal hourly rate
 *
 * The labour figure is what separates this from the gross profit on the
 * project page: a job that paid well can still have been a bad job once the
 * hours are counted. A project with no rate set has no labour cost, and its
 * margin reads the same as its gross one — honest, if less useful.
 *
 * Only billable time is counted. Unbillable minutes are real, but they are
 * not what this job cost the business to deliver.
 */
class ProjectProfitService
{
    public function __construct(private ProjectSummaryService $summaries) {}

    /**
     * Every project with its figures, worst margin last.
     *
     * @return array<int, array{
     *     id: int, name: string, client_name: string, status: string,
     *     status_label: string, status_tone: string, contract: int,
     *     invoiced: int, received: int, costs: int, minutes: int,
     *     hourly_rate: int, labour: int, profit: int, margin: ?float,
     * }>
     */
    public function report(): array
    {
        $projects = Project::query()->with('client')->get();
        $summaries = $this->summaries->all($projects->pluck('id')->all());
        $minutes = $this->billableMinutes();

        $rows = $projects->map(function (Project $project) use ($summaries, $minutes): array {
            $summary = $summaries[$project->id] ?? ProjectSummaryService::empty(
                $project->contract_amount,
            );

            $logged = $minutes[$project->id] ?? 0;
            $rate = $project->internal_hourly_rate ?? 0;

            // Minutes times a per-hour rate, kept in integer sen.
            $labour = intdiv($logged * $rate, 60);
            $profit = $summary['received'] - $summary['costs'] - $labour;

            return [
                'id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client->displayName(),
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'status_tone' => $project->status->tone(),
                'contract' => $summary['contract'],
                'invoiced' => $summary['invoiced'],
                'received' => $summary['received'],
                'costs' => $summary['costs'],
                'minutes' => $logged,
                'hourly_rate' => $rate,
                'labour' => $labour,
                'profit' => $profit,
                // No money in means no margin to speak of, not 0%.
                'margin' => $summary['received'] === 0
                    ? null
                    : round(($profit / $summary['received']) * 100, 1),
            ];
        });

        // Worst margin first: that is the row worth looking at.
        return $rows
            ->sortBy(fn (array $row) => $row['margin'] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /**
     * The figures for one project, for its own page.
     *
     * @return array<string, mixed>
     */
    public function for(Project $project): array
    {
        $summary = $this->summaries->for($project);
        $logged = $this->billableMinutes([$project->id])[$project->id] ?? 0;
        $rate = $project->internal_hourly_rate ?? 0;
        $labour = intdiv($logged * $rate, 60);

        return [
            ...$summary,
            'minutes' => $logged,
            'minutes_total' => $this->allMinutes($project),
            'hourly_rate' => $rate,
            'labour' => $labour,
            'profit_after_labour' => $summary['received'] - $summary['costs'] - $labour,
        ];
    }

    /**
     * Billable minutes per project, in one query.
     *
     * A running timer is left out: it has no end yet, so counting it would
     * make the figure move while nothing has been decided.
     *
     * @param  array<int, int>|null  $projectIds
     * @return array<int, int>
     */
    public function billableMinutes(?array $projectIds = null): array
    {
        return TimeEntry::query()
            ->billable()
            ->whereNotNull('minutes')
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->toBase()
            ->selectRaw('project_id, COALESCE(SUM(minutes), 0) AS total')
            ->groupBy('project_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->project_id => (int) $row->total])
            ->all();
    }

    /**
     * Every logged minute on a project, billable or not.
     */
    public function allMinutes(Project $project): int
    {
        return (int) $project->timeEntries()->whereNotNull('minutes')->sum('minutes');
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Project>
     */
    public function attach(Collection $projects): Collection
    {
        $summaries = $this->summaries->all($projects->pluck('id')->all());
        $minutes = $this->billableMinutes($projects->pluck('id')->all());

        return $projects->each(function (Project $project) use ($summaries, $minutes): void {
            $project->summary = $summaries[$project->id]
                ?? ProjectSummaryService::empty($project->contract_amount);
            $project->loggedMinutes = $minutes[$project->id] ?? 0;
        });
    }
}
