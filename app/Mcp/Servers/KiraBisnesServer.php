<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateInvoiceTool;
use App\Mcp\Tools\FundBalancesTool;
use App\Mcp\Tools\OutstandingInvoicesTool;
use App\Mcp\Tools\ProfitLossTool;
use App\Mcp\Tools\RecentTransactionsTool;
use App\Mcp\Tools\RecordInvoicePaymentTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Mcp\Tools\ReferenceDataTool;
use App\Mcp\Tools\SendInvoiceTool;
use App\Mcp\Tools\TaxSummaryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('KiraBisnes')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
The bookkeeping system for a one-person Malaysian IT business.

Money. Every amount you send or receive is in ringgit as a decimal number:
12.50 means RM 12.50. Never send sen. The currency is always MYR.

Ids. The write tools take ids, not names. Call reference_data first to look
up accounts, categories, clients and projects. Never guess an id — money
attached to the wrong account is wrong in a way no report will flag.

Funds. Income is automatically split across five funds (operating, owner
pay, tax, emergency, capital). You do not move money between funds; just
record the income and the split happens.

Before writing. record_transaction, create_invoice and record_payment all
change real accounting records. State plainly what you are about to record,
with the amount and the account, and let the owner correct you first.

Sending. send_invoice emails a real client and cannot be undone. Always get
the owner's explicit agreement, naming the client and the total, before
calling it. A draft invoice sitting unsent costs nothing; a wrong one in a
client's inbox costs a relationship.

Tax figures are estimates for planning. They are not an LHDN filing.
TXT)]
class KiraBisnesServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        // Read
        ReferenceDataTool::class,
        FundBalancesTool::class,
        ProfitLossTool::class,
        OutstandingInvoicesTool::class,
        RecentTransactionsTool::class,
        TaxSummaryTool::class,

        // Write
        RecordTransactionTool::class,
        CreateInvoiceTool::class,
        RecordInvoicePaymentTool::class,
        SendInvoiceTool::class,
    ];
}
