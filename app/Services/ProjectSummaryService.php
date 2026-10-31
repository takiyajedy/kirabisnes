<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * The money side of a project: what was billed, what came in, what it cost,
 * and the gross profit (PLAN.md phase 3).
 *
 * Gross profit counts only the money actually tagged to the project, so it
 * answers "did this job pay for itself" rather than "was it invoiced".
 * Your own time is not in it — that needs the hourly rate and the time
 * entries from phase 5.
 *
 * @phpstan-type ProjectFigures array{
 *     contract: int, invoiced: int, received: int, outstanding: int,
 *     costs: int, gross_profit: int,
 * }
 */
class ProjectSummaryService
{
    /**
     * @return ProjectFigures
     */
    public function for(Project $project): array
    {
        return $this->all([$project->id])[$project->id]
            ?? self::empty($project->contract_amount);
    }

    /**
     * Every project's figures in two queries.
     *
     * @param  array<int, int>|null  $projectIds
     * @return array<int, ProjectFigures>
     */
    public function all(?array $projectIds = null): array
    {
        $invoiced = Invoice::query()
            ->whereNotNull('project_id')
            ->whereNot('status', InvoiceStatus::Void->value)
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->toBase()
            ->selectRaw('project_id')
            ->selectRaw('COALESCE(SUM(total), 0) AS invoiced')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) AS paid')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $tagged = Transaction::query()
            ->whereNotNull('project_id')
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->toBase()
            ->selectRaw('project_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense")
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $contracts = Project::query()
            ->when($projectIds !== null, fn ($q) => $q->whereIn('id', $projectIds))
            ->pluck('contract_amount', 'id');

        $summaries = [];

        foreach ($contracts as $id => $contract) {
            $billed = (int) ($invoiced->get($id)->invoiced ?? 0);
            $paid = (int) ($invoiced->get($id)->paid ?? 0);
            $income = (int) ($tagged->get($id)->income ?? 0);
            $costs = (int) ($tagged->get($id)->expense ?? 0);

            $summaries[(int) $id] = [
                'contract' => (int) $contract,
                'invoiced' => $billed,
                'received' => $income,
                'outstanding' => max(0, $billed - $paid),
                'costs' => $costs,
                'gross_profit' => $income - $costs,
            ];
        }

        return $summaries;
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Project>
     */
    public function attach(Collection $projects): Collection
    {
        $summaries = $this->all($projects->pluck('id')->all());

        return $projects->each(function (Project $project) use ($summaries): void {
            $project->summary = $summaries[$project->id]
                ?? self::empty($project->contract_amount);
        });
    }

    /**
     * @return ProjectFigures
     */
    public static function empty(int $contract = 0): array
    {
        return [
            'contract' => $contract,
            'invoiced' => 0,
            'received' => 0,
            'outstanding' => 0,
            'costs' => 0,
            'gross_profit' => 0,
        ];
    }
}
