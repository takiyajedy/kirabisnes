<?php

namespace App\Http\Controllers;

use App\Enums\DashboardPeriod;
use App\Http\Resources\FundResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\RecurringItemResource;
use App\Http\Resources\TransactionResource;
use App\Services\DashboardService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function __invoke(Request $request): Response
    {
        // A bookmarked or hand-edited query string should still show the
        // dashboard, so bad input falls back to the current month rather than
        // failing validation — the same approach the transactions filters take.
        $period = Period::resolve(
            $request->string('period')->value(),
            $request->string('from')->value(),
            $request->string('to')->value(),
        );

        return Inertia::render('Dashboard', [
            'period' => [
                ...$period->toArray(),
                'comparison_label' => $period->preset->comparisonLabel(),
            ],
            'periodOptions' => DashboardPeriod::options(),
            'summary' => $this->dashboard->summary($period),
            'totalBalance' => $this->dashboard->totalBalance(),
            'funds' => FundResource::collection($this->dashboard->funds())->resolve(),
            // Month names are formatted in the browser, so these stay ISO.
            'monthly' => $this->dashboard->monthlySeries($period->chartEndsOn()),
            'expensesByCategory' => $this->dashboard->expensesByCategory($period),
            'recent' => TransactionResource::collection(
                $this->dashboard->recentTransactions($period),
            )->resolve(),
            // Money owed is not scoped to the chosen window.
            'outstandingInvoices' => $this->dashboard->outstandingInvoices(),
            'invoicesDueSoon' => InvoiceResource::collection(
                $this->dashboard->invoicesDueSoon(),
            )->resolve(),
            // What the subscriptions are expected to do next month.
            'upcomingRecurring' => $this->dashboard->upcomingRecurring(),
            'upcomingRecurringItems' => RecurringItemResource::collection(
                $this->dashboard->upcomingRecurringItems(),
            )->resolve(),
        ]);
    }
}
