<?php

namespace App\Http\Controllers;

use App\Actions\Funds\TransferBetweenFunds;
use App\Http\Requests\Funds\FundPercentageRequest;
use App\Http\Requests\Funds\FundTransferRequest;
use App\Http\Resources\FundResource;
use App\Models\Fund;
use App\Services\FundBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FundController extends Controller
{
    public function __construct(private FundBalanceService $balances) {}

    public function index(): Response
    {
        $balances = $this->balances->all();

        $funds = Fund::query()->ordered()->get()
            ->each(fn (Fund $fund) => $fund->balance = $balances[$fund->id] ?? 0);

        return Inertia::render('Funds/Index', [
            'funds' => FundResource::collection($funds)->resolve(),
            'totalBalance' => $this->balances->total(),
        ]);
    }

    /**
     * Save the percentage split. The active shares must add up to 100.
     */
    public function update(FundPercentageRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated()['funds'] as $row) {
                Fund::query()->whereKey($row['id'])->update([
                    'percentage' => $row['percentage'],
                    'is_active' => $row['is_active'],
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('fund.percentages_updated'),
        ]);

        return to_route('funds.index');
    }

    /**
     * Move a label from one fund to another. No real money moves.
     */
    public function transfer(
        FundTransferRequest $request,
        TransferBetweenFunds $transfer,
    ): RedirectResponse {
        $data = $request->validated();

        $transfer->handle(
            from: Fund::query()->whereKey($data['from_fund_id'])->firstOrFail(),
            to: Fund::query()->whereKey($data['to_fund_id'])->firstOrFail(),
            amount: $data['amount'],
            date: $data['date'],
            note: $data['note'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('fund.transferred'),
        ]);

        return to_route('funds.index');
    }
}
