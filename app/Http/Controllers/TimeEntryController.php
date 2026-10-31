<?php

namespace App\Http\Controllers;

use App\Actions\Time\StartTimer;
use App\Actions\Time\StopTimer;
use App\Http\Requests\Time\TimeEntryRequest;
use App\Models\Project;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TimeEntryController extends Controller
{
    /**
     * Start the clock on a project.
     */
    public function start(Project $project, StartTimer $start): RedirectResponse
    {
        $start->handle($project);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('time.started', ['project' => $project->name]),
        ]);

        return back();
    }

    /**
     * Stop whichever timer is running.
     */
    public function stop(StopTimer $stop): RedirectResponse
    {
        $running = TimeEntry::query()->running()->first();

        if ($running === null) {
            return back();
        }

        $entry = $stop->handle($running);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('time.stopped', [
                'minutes' => (string) $entry->minutes,
            ]),
        ]);

        return back();
    }

    /**
     * Log a stretch of work that was not timed.
     */
    public function store(TimeEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        TimeEntry::query()->create([
            ...$validated,
            // The end follows from the start and the length, so the entry
            // reads the same whether it was timed or typed.
            'ended_at' => CarbonImmutable::parse($validated['started_at'])
                ->addMinutes($validated['minutes']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('time.logged'),
        ]);

        return back();
    }

    public function update(TimeEntryRequest $request, TimeEntry $timeEntry): RedirectResponse
    {
        $validated = $request->validated();

        $timeEntry->update([
            ...$validated,
            'ended_at' => CarbonImmutable::parse($validated['started_at'])
                ->addMinutes($validated['minutes']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('time.updated'),
        ]);

        return back();
    }

    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        $timeEntry->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('time.deleted'),
        ]);

        return back();
    }
}
