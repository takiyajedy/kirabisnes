<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Moves sent and part-paid invoices to overdue once their due date has gone
 * by (PLAN.md phase 3). Runs daily; see routes/console.php.
 */
class MarkInvoicesOverdue extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark invoices overdue once their due date has passed';

    public function handle(): int
    {
        // An invoice due today is due by the end of today, so only dates
        // strictly before today count as late.
        $today = CarbonImmutable::today();

        $marked = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Partial->value])
            ->whereDate('due_date', '<', $today->toDateString())
            ->update(['status' => InvoiceStatus::Overdue->value]);

        // An invoice whose due date was pushed back is no longer late.
        $cleared = Invoice::query()
            ->where('status', InvoiceStatus::Overdue->value)
            ->whereDate('due_date', '>=', $today->toDateString())
            ->update(['status' => InvoiceStatus::Sent->value]);

        $this->info("Marked {$marked} invoice(s) overdue, cleared {$cleared}.");

        return self::SUCCESS;
    }
}
