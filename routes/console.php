<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// An invoice falls overdue the morning after its due date (PLAN.md phase 3).
Schedule::command('invoices:mark-overdue')->dailyAt('01:00');

// Draft the renewals that are coming up and nudge about the costs that are
// not drafted automatically (PLAN.md phase 4).
Schedule::command('recurring:process')->dailyAt('01:15');

// A nightly copy of the books and the files attached to them (PLAN.md 6).
// The clean-up runs first so the night's backup is never the one pruned.
Schedule::command('backup:clean')->dailyAt('02:00');
Schedule::command('backup:run')->dailyAt('02:15');
