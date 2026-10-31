<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\StoreTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Enums\TransactionType;
use App\Http\Requests\Transactions\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Fund;
use App\Models\Transaction;
use App\Services\FundAllocationService;
use App\Support\Money;
use App\Support\TransactionFormOptions;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $transactions = Transaction::query()
            ->with(['account', 'toAccount', 'category', 'fund'])
            ->when($filters['type'], fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['account_id'], fn (Builder $q, int $id) => $q
                ->where(fn (Builder $inner) => $inner
                    ->where('account_id', $id)
                    ->orWhere('to_account_id', $id)))
            ->when($filters['category_id'], fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->when($filters['search'], fn (Builder $q, string $search) => $q
                ->where(fn (Builder $inner) => $inner
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")))
            ->between($filters['from'], $filters['to'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Transactions/Index', [
            'transactions' => TransactionResource::collection($transactions)->response()->getData(true),
            'filters' => $filters,
            'formOptions' => TransactionFormOptions::all(),
        ]);
    }

    public function store(TransactionRequest $request, StoreTransaction $store): RedirectResponse
    {
        $attributes = $request->toAttributes();
        $attributes['receipt_path'] = $this->storeReceipt($request->file('receipt'));

        $store->handle($attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('transaction.created'),
        ]);

        return back();
    }

    public function update(
        TransactionRequest $request,
        Transaction $transaction,
        UpdateTransaction $update,
    ): RedirectResponse {
        $attributes = $request->toAttributes();
        $attributes['receipt_path'] = $this->resolveReceipt($request, $transaction);

        $update->handle($transaction, $attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('transaction.updated'),
        ]);

        return back();
    }

    public function destroy(Transaction $transaction, DeleteTransaction $delete): RedirectResponse
    {
        $delete->handle($transaction);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('transaction.deleted'),
        ]);

        return back();
    }

    /**
     * Serve a receipt from the private disk.
     */
    public function receipt(Transaction $transaction): StreamedResponse|Responsable
    {
        abort_if($transaction->receipt_path === null, 404);

        return Storage::disk('local')->response($transaction->receipt_path);
    }

    /**
     * The options the transaction form needs, for the quick dialog in the top
     * bar: it can be opened from any page, so the data is fetched on demand
     * rather than shared with every response.
     */
    public function options(): JsonResponse
    {
        return response()->json(TransactionFormOptions::all());
    }

    /**
     * Preview how an income would be split, for the transaction form.
     *
     * The split lives in FundAllocationService so there is only ever one copy
     * of the maths (PLAN.md section 3.2).
     */
    public function allocationPreview(
        Request $request,
        FundAllocationService $allocation,
    ): JsonResponse {
        // Ringgit in, like every other endpoint that takes money, so there is
        // one rule rather than one rule and an exception (PLAN.md 3.1).
        $amount = max(0, Money::toSen($request->input('amount')));
        $funds = $allocation->activeFunds();

        $shares = FundAllocationService::split($amount, $funds);

        return response()->json([
            'amount' => $amount,
            'shares' => $funds->map(fn (Fund $fund) => [
                'id' => $fund->id,
                'name' => $fund->name,
                'color' => $fund->color,
                'percentage' => (float) $fund->percentage,
                'amount' => $shares[$fund->id] ?? 0,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array{type: ?string, account_id: ?int, category_id: ?int, from: ?string, to: ?string, search: ?string}
     */
    private function filters(Request $request): array
    {
        $type = $request->string('type')->value();

        return [
            'type' => in_array($type, TransactionType::values(), true) ? $type : null,
            'account_id' => $request->integer('account_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'search' => $request->string('search')->trim()->value() ?: null,
        ];
    }

    private function storeReceipt(?UploadedFile $file): ?string
    {
        // Receipts can hold personal data, so they stay on the private disk.
        return $file?->store('receipts', 'local') ?: null;
    }

    private function resolveReceipt(TransactionRequest $request, Transaction $transaction): ?string
    {
        if ($request->boolean('remove_receipt')) {
            $this->deleteReceipt($transaction);

            return null;
        }

        if ($request->hasFile('receipt')) {
            $this->deleteReceipt($transaction);

            return $this->storeReceipt($request->file('receipt'));
        }

        return $transaction->receipt_path;
    }

    private function deleteReceipt(Transaction $transaction): void
    {
        if ($transaction->receipt_path !== null) {
            Storage::disk('local')->delete($transaction->receipt_path);
        }
    }
}
