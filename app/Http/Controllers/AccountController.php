<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\Accounts\AccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private AccountBalanceService $balances) {}

    public function index(): Response
    {
        $balances = $this->balances->all();

        $accounts = Account::query()->ordered()->get()
            ->each(fn (Account $account) => $account->balance = $balances[$account->id] ?? 0);

        return Inertia::render('Accounts/Index', [
            'accounts' => AccountResource::collection($accounts)->resolve(),
            'totalBalance' => $this->balances->totalActive(),
            'accountTypes' => AccountType::options(),
        ]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        Account::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('account.created'),
        ]);

        return to_route('accounts.index');
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('account.updated'),
        ]);

        return to_route('accounts.index');
    }

    public function destroy(Account $account): RedirectResponse
    {
        // Deleting an account would strand its transactions, so an account
        // that has been used is switched off instead.
        if ($account->transactions()->exists() || $account->incomingTransfers()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('account.error.in_use'),
            ]);

            return to_route('accounts.index');
        }

        $account->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('account.deleted'),
        ]);

        return to_route('accounts.index');
    }
}
