<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

/**
 * The nightly backup (PLAN.md phase 6).
 *
 * The database dump carries the books; the included directories carry the
 * files attached to them. The code is in version control and is not here.
 */
it('backs the database up to the configured disk', function () {
    Storage::fake('local');

    $this->artisan('backup:run', ['--only-db' => true, '--disable-notifications' => true])
        ->assertSuccessful();

    $files = Storage::disk('local')->allFiles();

    expect($files)->not->toBeEmpty()
        ->and(collect($files)->first(fn (string $f) => str_ends_with($f, '.zip')))
        ->not->toBeNull();
});

it('includes the files attached to the books, and not the code', function () {
    $include = config('backup.backup.source.files.include');

    // Receipts and the logo live here; `vendor` and the app code do not.
    expect($include)->toContain(storage_path('app/private'))
        ->toContain(storage_path('app/public'))
        ->not->toContain(base_path());
});

it('takes its destination from the environment', function () {
    // Adding a remote disk is one line in .env, not an edit to the config.
    expect(config('backup.backup.destination.disks'))->toBe(['local']);
});

it('is scheduled nightly, with the clean-up first', function () {
    $schedule = app(Schedule::class);

    $commands = collect($schedule->events())
        ->mapWithKeys(fn ($event) => [
            // "php artisan backup:run" out of the full command line.
            trim((string) preg_replace('/.*artisan[\'"]? /', '', $event->command)) => $event->expression,
        ]);

    expect($commands)->toHaveKey('backup:run')
        ->toHaveKey('backup:clean')
        // The clean-up runs first so the night's backup is never the one
        // that gets pruned.
        ->and($commands['backup:clean'])->toBe('0 2 * * *')
        ->and($commands['backup:run'])->toBe('15 2 * * *');
});
