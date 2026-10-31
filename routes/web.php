<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BankImportController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnerPayController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RecurringItemController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// The manifest carries the business's own name, so it is served rather than
// kept as a static file (PLAN.md phase 6).
Route::get('manifest.webmanifest', ManifestController::class)->name('manifest');

/*
 * The client-facing invoice page (PLAN.md phase 6).
 *
 * Outside the auth group on purpose: the signature on the URL is the
 * authorisation, so a client can open and pay an invoice without an account.
 */
Route::middleware('signed')->group(function () {
    Route::get('i/{invoice}', [PublicInvoiceController::class, 'show'])->name('public.invoice');
    Route::get('i/{invoice}/pdf', [PublicInvoiceController::class, 'pdf'])->name('public.invoice.pdf');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Accounts
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    // Funds
    Route::get('funds', [FundController::class, 'index'])->name('funds.index');
    Route::patch('funds', [FundController::class, 'update'])->name('funds.update');
    Route::post('funds/transfer', [FundController::class, 'transfer'])->name('funds.transfer');

    // Clients
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::patch('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    // Projects
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Quotations. The static segments come before {quotation} so "create"
    // is never read as a document id.
    Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
    Route::patch('quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
    Route::patch('quotations/{quotation}/status', [QuotationController::class, 'status'])->name('quotations.status');
    Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');

    // Invoices
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::delete('invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'removePayment'])->name('invoices.payments.destroy');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    // Bank statement import
    Route::get('import', [BankImportController::class, 'index'])->name('import.index');
    Route::post('import/preview', [BankImportController::class, 'preview'])->name('import.preview');
    Route::post('import', [BankImportController::class, 'store'])->name('import.store');
    Route::delete('import', [BankImportController::class, 'destroy'])->name('import.destroy');

    // Reports. The export route comes last so "export" is never read as a
    // report name.
    Route::get('reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('reports/tax', [ReportController::class, 'tax'])->name('reports.tax');
    Route::get('reports/projects', [ReportController::class, 'projects'])->name('reports.projects');
    Route::get('reports/forecast', [ReportController::class, 'forecast'])->name('reports.forecast');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');

    // Time tracking
    Route::post('projects/{project}/timer', [TimeEntryController::class, 'start'])->name('time.start');
    Route::post('timer/stop', [TimeEntryController::class, 'stop'])->name('time.stop');
    Route::post('time-entries', [TimeEntryController::class, 'store'])->name('time.store');
    Route::patch('time-entries/{timeEntry}', [TimeEntryController::class, 'update'])->name('time.update');
    Route::delete('time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('time.destroy');

    // Recurring items
    Route::get('recurring', [RecurringItemController::class, 'index'])->name('recurring.index');
    Route::post('recurring', [RecurringItemController::class, 'store'])->name('recurring.store');
    Route::patch('recurring/{recurring}', [RecurringItemController::class, 'update'])->name('recurring.update');
    Route::post('recurring/{recurring}/pay', [RecurringItemController::class, 'pay'])->name('recurring.pay');
    Route::post('recurring/{recurring}/skip', [RecurringItemController::class, 'skip'])->name('recurring.skip');
    Route::delete('recurring/{recurring}', [RecurringItemController::class, 'destroy'])->name('recurring.destroy');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Owner pay
    Route::get('owner-pay', [OwnerPayController::class, 'index'])->name('owner-pay.index');

    // Transactions
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::post('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::get('transactions/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('transactions.receipt');
    Route::get('transaction-options', [TransactionController::class, 'options'])->name('transactions.options');
    Route::get('allocation-preview', [TransactionController::class, 'allocationPreview'])->name('transactions.allocation-preview');
});

require __DIR__.'/settings.php';
