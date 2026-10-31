<?php

namespace App\Console\Commands;

use App\Actions\Recurring\GenerateRecurringInvoice;
use App\Enums\RecurringKind;
use App\Models\RecurringItem;
use App\Models\User;
use App\Notifications\RecurringCostDue;
use App\Notifications\RecurringInvoiceDrafted;
use App\Services\RecurringScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The daily pass over the recurring items (PLAN.md phase 4).
 *
 *   client billing + auto generate -> draft the invoice, then move the item
 *                                     on to its next charge date
 *   client billing, by hand        -> remind the owner that it is due
 *   own cost                       -> remind the owner; the item only moves
 *                                     on when they mark it paid
 *
 * An item is picked up once `next_date - remind_days_before` has arrived, so
 * a renewal is drafted before the client is charged rather than after.
 *
 * Running twice in a day changes nothing: an invoice is only written if that
 * item has none for that charge date, and a reminder is only sent once per
 * charge.
 */
class ProcessRecurringItems extends Command
{
    protected $signature = 'recurring:process {--date= : Run as if it were this date}';

    protected $description = 'Draft recurring invoices and send renewal reminders';

    public function handle(
        GenerateRecurringInvoice $generate,
        RecurringScheduleService $schedule,
    ): int {
        $today = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $owners = User::query()->get();

        $drafted = 0;
        $reminded = 0;

        foreach ($this->dueItems($today) as $item) {
            if ($item->kind === RecurringKind::ClientBilling && $item->auto_generate) {
                $drafted += $this->draftInvoices($item, $today, $owners, $generate, $schedule);

                continue;
            }

            // Everything else is a nudge: a client charge the owner wants to
            // raise themselves, or a cost of their own to pay.
            if ($this->remind($item, $today, $owners)) {
                $reminded++;
            }
        }

        $this->info("recurring:process — {$drafted} invoice(s) drafted, {$reminded} reminder(s) sent.");

        return self::SUCCESS;
    }

    /**
     * The active items whose warning window has opened.
     *
     * @return Collection<int, RecurringItem>
     */
    private function dueItems(CarbonImmutable $today): Collection
    {
        return RecurringItem::query()
            ->with(['client', 'category', 'account'])
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (RecurringItem $item) => $item->isDue($today))
            ->values();
    }

    /**
     * Draft every invoice this item owes, up to and including today's window.
     *
     * An app that was not opened for a few months really does owe an invoice
     * for each charge that went by, so the loop catches up rather than
     * skipping to the present.
     *
     * @param  Collection<int, User>  $owners
     */
    private function draftInvoices(
        RecurringItem $item,
        CarbonImmutable $today,
        Collection $owners,
        GenerateRecurringInvoice $generate,
        RecurringScheduleService $schedule,
    ): int {
        $drafted = 0;

        for ($i = 0; $i < RecurringScheduleService::MAX_CATCH_UP; $i++) {
            if (! $item->isDue($today)) {
                break;
            }

            $invoice = $generate->handle($item);

            if ($invoice !== null) {
                $drafted++;

                foreach ($owners as $owner) {
                    $owner->notify(new RecurringInvoiceDrafted($item, $invoice));
                }
            }

            // Move on whether or not an invoice was written: if one already
            // exists for this charge, this item is done with that period.
            $schedule->advance($item);
            $item->update(['last_reminded_at' => $today]);
        }

        return $drafted;
    }

    /**
     * Tell the owners about a charge, once per charge.
     *
     * @param  Collection<int, User>  $owners
     */
    private function remind(
        RecurringItem $item,
        CarbonImmutable $today,
        Collection $owners,
    ): bool {
        // An own cost is only moved along when it is marked paid, so without
        // this check the reminder would arrive every day of the window.
        $alreadyTold = $item->last_reminded_at !== null
            && $item->last_reminded_at->greaterThanOrEqualTo($item->remindsOn());

        if ($alreadyTold) {
            return false;
        }

        foreach ($owners as $owner) {
            $owner->notify(new RecurringCostDue($item));
        }

        $item->update(['last_reminded_at' => $today]);

        return true;
    }
}
