<?php

use App\Actions\Time\StartTimer;
use App\Actions\Time\StopTimer;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->actingAs(User::factory()->create());
    $this->project = Project::factory()->create([
        'name' => 'Portal',
        'internal_hourly_rate' => 80_00,
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('starts the timer on a project', function () {
    $this->from(route('projects.show', $this->project))
        ->post(route('time.start', $this->project))
        ->assertInertiaFlash('toast.type', 'success');

    $entry = TimeEntry::query()->firstOrFail();

    expect($entry->project_id)->toBe($this->project->id)
        ->and($entry->isRunning())->toBeTrue()
        ->and($entry->minutes)->toBeNull()
        ->and($entry->billable)->toBeTrue()
        ->and($entry->started_at->toDateTimeString())->toBe('2026-10-15 09:00:00');
});

it('stops the timer and writes down the minutes', function () {
    $this->post(route('time.start', $this->project));

    CarbonImmutable::setTestNow('2026-10-15 11:30:00');

    $this->from(route('projects.show', $this->project))
        ->post(route('time.stop'))
        ->assertInertiaFlash('toast.type', 'success');

    $entry = TimeEntry::query()->firstOrFail();

    expect($entry->isRunning())->toBeFalse()
        ->and($entry->minutes)->toBe(150)
        ->and($entry->ended_at?->toDateTimeString())->toBe('2026-10-15 11:30:00');
});

it('runs only one timer at a time', function () {
    $other = Project::factory()->create(['name' => 'Lain']);

    $this->post(route('time.start', $this->project));

    CarbonImmutable::setTestNow('2026-10-15 10:00:00');

    // Starting a second one stops the first: the owner has plainly moved on.
    $this->post(route('time.start', $other));

    $entries = TimeEntry::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->isRunning())->toBeFalse()
        ->and($entries[0]->minutes)->toBe(60)
        ->and($entries[1]->isRunning())->toBeTrue()
        ->and(TimeEntry::query()->running()->count())->toBe(1);
});

it('counts a timer stopped within seconds as a minute', function () {
    $entry = app(StartTimer::class)->handle($this->project);

    // Zero would read as if nothing had happened.
    app(StopTimer::class)->handle(
        $entry,
        CarbonImmutable::parse('2026-10-15 09:00:20'),
    );

    expect($entry->refresh()->minutes)->toBe(1);
});

it('does nothing when there is no timer to stop', function () {
    $this->from(route('projects.show', $this->project))
        ->post(route('time.stop'))
        ->assertRedirect(route('projects.show', $this->project));

    expect(TimeEntry::query()->count())->toBe(0);
});

it('leaves a stopped entry alone', function () {
    $entry = TimeEntry::factory()->lasting(90)->create([
        'project_id' => $this->project->id,
    ]);

    app(StopTimer::class)->handle($entry, CarbonImmutable::parse('2027-01-01'));

    expect($entry->refresh()->minutes)->toBe(90);
});

it('logs a stretch of work that was not timed', function () {
    $this->from(route('projects.show', $this->project))
        ->post(route('time.store'), [
            'project_id' => $this->project->id,
            'started_at' => '2026-10-14 14:00:00',
            'minutes' => 135,
            'note' => 'Mesyuarat & semakan',
            'billable' => true,
        ])
        ->assertInertiaFlash('toast.type', 'success');

    $entry = TimeEntry::query()->firstOrFail();

    expect($entry->minutes)->toBe(135)
        ->and($entry->note)->toBe('Mesyuarat & semakan')
        // The end follows from the start and the length.
        ->and($entry->ended_at?->toDateTimeString())->toBe('2026-10-14 16:15:00');
});

it('refuses a nonsense length', function () {
    foreach ([0, -5, 20_000] as $minutes) {
        $this->from(route('projects.show', $this->project))
            ->post(route('time.store'), [
                'project_id' => $this->project->id,
                'started_at' => '2026-10-14 14:00:00',
                'minutes' => $minutes,
                'billable' => true,
            ])
            ->assertSessionHasErrors('minutes');
    }

    expect(TimeEntry::query()->count())->toBe(0);
});

it('edits an entry and moves its end with it', function () {
    $entry = TimeEntry::factory()->lasting(60)->startingAt('2026-10-10 09:00:00')
        ->create(['project_id' => $this->project->id]);

    $this->patch(route('time.update', $entry), [
        'project_id' => $this->project->id,
        'started_at' => '2026-10-10 09:00:00',
        'minutes' => 240,
        'billable' => false,
    ])->assertRedirect();

    $entry->refresh();

    expect($entry->minutes)->toBe(240)
        ->and($entry->billable)->toBeFalse()
        ->and($entry->ended_at?->toDateTimeString())->toBe('2026-10-10 13:00:00');
});

it('deletes an entry', function () {
    $entry = TimeEntry::factory()->lasting(60)->create([
        'project_id' => $this->project->id,
    ]);

    $this->from(route('projects.show', $this->project))
        ->delete(route('time.destroy', $entry))
        ->assertInertiaFlash('toast.type', 'success');

    expect(TimeEntry::query()->count())->toBe(0);
});

it('shares the running timer with every page', function () {
    $this->post(route('time.start', $this->project));

    CarbonImmutable::setTestNow('2026-10-15 09:45:00');

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('runningTimer.project_id', $this->project->id)
            ->where('runningTimer.project_name', 'Portal')
            ->where('runningTimer.is_running', true)
            ->where('runningTimer.elapsed_minutes', 45),
        );
});

it('shares nothing when no timer is running', function () {
    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('runningTimer', null));
});

it('shows the logged time and its cost on the project page', function () {
    TimeEntry::factory()->lasting(120)->create(['project_id' => $this->project->id]);
    TimeEntry::factory()->lasting(60)->unbillable()->create([
        'project_id' => $this->project->id,
    ]);

    $this->get(route('projects.show', $this->project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('timeEntries', 2)
            // Billable only, costed at RM80 an hour.
            ->where('profit.minutes', 120)
            ->where('profit.minutes_total', 180)
            ->where('profit.hourly_rate', 80_00)
            ->where('profit.labour', 160_00),
        );
});

it('keeps guests out', function () {
    auth()->logout();

    $this->post(route('time.start', $this->project))->assertRedirect(route('login'));
    $this->post(route('time.stop'))->assertRedirect(route('login'));
});
