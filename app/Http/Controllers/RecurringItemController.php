<?php

namespace App\Http\Controllers;

use App\Actions\Recurring\PayRecurringCost;
use App\Enums\CategoryType;
use App\Enums\RecurringInterval;
use App\Enums\RecurringKind;
use App\Http\Requests\Recurring\RecurringItemRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\RecurringItemResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Models\RecurringItem;
use App\Services\RecurringScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringItemController extends Controller
{
    public function index(Request $request): Response
    {
        $kind = $request->string('kind')->value();
        $kind = in_array($kind, RecurringKind::values(), true)
            ? RecurringKind::from($kind)
            : RecurringKind::ClientBilling;

        $items = RecurringItem::query()
            ->with(['client', 'project', 'category', 'account'])
            ->ofKind($kind)
            ->ordered()
            ->get();

        return Inertia::render('Recurring/Index', [
            'items' => RecurringItemResource::collection($items)->resolve(),
            'kind' => $kind->value,
            'kinds' => RecurringKind::options(),
            'counts' => [
                RecurringKind::ClientBilling->value => RecurringItem::query()
                    ->ofKind(RecurringKind::ClientBilling)->count(),
                RecurringKind::OwnCost->value => RecurringItem::query()
                    ->ofKind(RecurringKind::OwnCost)->count(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function store(RecurringItemRequest $request): RedirectResponse
    {
        RecurringItem::query()->create($request->toAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('recurring.created'),
        ]);

        return back();
    }

    public function update(
        RecurringItemRequest $request,
        RecurringItem $recurring,
    ): RedirectResponse {
        $recurring->update($request->toAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('recurring.updated'),
        ]);

        return back();
    }

    /**
     * Record a recurring own cost as paid and move it on.
     */
    public function pay(
        Request $request,
        RecurringItem $recurring,
        PayRecurringCost $pay,
    ): RedirectResponse {
        abort_unless($recurring->kind === RecurringKind::OwnCost, 403);

        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'date' => ['required', 'date'],
        ]);

        $pay->handle($recurring, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('recurring.paid', ['name' => $recurring->name]),
        ]);

        return back();
    }

    /**
     * Move an item on without recording anything.
     *
     * A client charge the owner raises by hand has nothing to record here —
     * the invoice is made on the invoices page — but the item still has to
     * move on, or it stays stuck on a date that has gone by.
     */
    public function skip(
        RecurringItem $recurring,
        RecurringScheduleService $schedule,
    ): RedirectResponse {
        $schedule->advance($recurring);
        $recurring->update(['last_reminded_at' => null]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('recurring.skipped', [
                'date' => $recurring->next_date->toDateString(),
            ]),
        ]);

        return back();
    }

    public function destroy(RecurringItem $recurring): RedirectResponse
    {
        $recurring->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('recurring.deleted'),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'intervals' => RecurringInterval::options(),
            'clients' => ClientResource::collection(Client::query()->ordered()->get())->resolve(),
            'projects' => ProjectResource::collection(
                Project::query()->with('client')->ordered()->get(),
            )->resolve(),
            'expenseCategories' => CategoryResource::collection(
                Category::query()->where('type', CategoryType::Expense)->ordered()->get(),
            )->resolve(),
            'accounts' => AccountResource::collection(
                Account::query()->active()->ordered()->get(),
            )->resolve(),
        ];
    }
}
