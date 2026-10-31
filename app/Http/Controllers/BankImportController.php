<?php

namespace App\Http\Controllers;

use App\Http\Requests\Import\BankImportRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\BankImportService;
use App\Support\BankStatement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Importing a bank statement, in three steps: upload, map, confirm
 * (PLAN.md phase 6).
 *
 * Nothing is written until the owner has seen the preview. The parsed rows
 * are held in the session between the two steps rather than the file being
 * kept on disk: the import is a single sitting, and a statement is the sort
 * of file that should not be left lying around.
 */
class BankImportController extends Controller
{
    /** Where the preview lives between the two requests. */
    private const SESSION_KEY = 'bank_import.preview';

    public function index(Request $request): Response
    {
        return Inertia::render('Import/Index', [
            'accounts' => AccountResource::collection(
                Account::query()->active()->ordered()->get(),
            )->resolve(),
            'dateFormats' => BankImportService::dateFormats(),
            'matchDays' => BankImportService::MATCH_DAYS,
            // Held from the previous request, if the owner has just uploaded.
            'preview' => $request->session()->get(self::SESSION_KEY),
        ]);
    }

    /**
     * Read the file and show what each row would do.
     */
    public function preview(
        BankImportRequest $request,
        BankImportService $service,
    ): RedirectResponse {
        $validated = $request->validated();
        $account = Account::query()->whereKey($validated['account_id'])->firstOrFail();

        $statement = BankStatement::fromFile(
            $request->file('statement')->getRealPath(),
            (bool) $validated['has_headers'],
        );

        if ($statement->rows === []) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('import.error.empty_file'),
            ]);

            return back();
        }

        $rows = $service->preview(
            $statement,
            $request->mapping(),
            $account,
            (string) $validated['date_format'],
        );

        Session::put(self::SESSION_KEY, [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'columns' => $statement->columns(),
            'rows' => $rows,
            'counts' => [
                'new' => count(array_filter($rows, fn (array $r) => $r['status'] === 'new')),
                'matched' => count(array_filter($rows, fn (array $r) => $r['status'] === 'matched')),
                'skipped' => count(array_filter($rows, fn (array $r) => $r['status'] === 'skipped')),
            ],
        ]);

        return to_route('import.index');
    }

    /**
     * Write the rows the owner ticked.
     */
    public function store(
        Request $request,
        BankImportService $service,
    ): RedirectResponse {
        $preview = $request->session()->get(self::SESSION_KEY);

        if ($preview === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('import.error.expired'),
            ]);

            return to_route('import.index');
        }

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['integer', 'min:1'],
        ]);

        $account = Account::query()->whereKey($preview['account_id'])->firstOrFail();

        $result = $service->import($preview['rows'], $validated['rows'], $account);

        // The preview describes a file that has now been acted on.
        $request->session()->forget(self::SESSION_KEY);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('import.done', [
                'imported' => (string) $result['imported'],
            ]),
        ]);

        return to_route('transactions.index');
    }

    /**
     * Throw the preview away and start again.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return to_route('import.index');
    }
}
