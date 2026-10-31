export type AccountType = 'bank' | 'ewallet' | 'cash';
export type CategoryType = 'income' | 'expense';
export type TransactionTypeValue =
    | 'income'
    | 'expense'
    | 'transfer'
    | 'owner_draw'
    | 'capital_injection';

/** A value/label pair, already translated by the server. */
export type EnumOption = {
    value: string;
    label: string;
};

export type Account = {
    id: number;
    name: string;
    type: AccountType;
    type_label: string;
    bank_name: string | null;
    /** Sen. */
    opening_balance: number;
    /** Sen. Derived, only present on the accounts page. */
    balance?: number;
    is_active: boolean;
    sort_order: number;
};

export type Fund = {
    id: number;
    /** Already in the active UI language. */
    name: string;
    slug: string;
    percentage: number;
    color: string;
    sort_order: number;
    is_active: boolean;
    is_system: boolean;
    /** Sen. Derived, only present on the funds page. */
    balance?: number;
};

export type Category = {
    id: number;
    name: string;
    name_ms: string;
    name_en: string;
    type: CategoryType;
    type_label: string;
    default_fund_id: number | null;
    tax_deductible_default: boolean;
    color: string;
    is_system: boolean;
    transactions_count?: number;
};

export type Transaction = {
    id: number;
    type: TransactionTypeValue;
    type_label: string;
    account_id: number;
    account_name?: string;
    to_account_id: number | null;
    to_account_name?: string | null;
    /** Sen, always positive. The type says which way the money went. */
    amount: number;
    date: string;
    description: string;
    reference: string | null;
    category_id: number | null;
    category_name?: string | null;
    fund_id: number | null;
    fund_name?: string | null;
    tax_deductible: boolean;
    receipt_url: string | null;
    has_receipt: boolean;
};

export type TransactionFilters = {
    type: string | null;
    account_id: number | null;
    category_id: number | null;
    from: string | null;
    to: string | null;
    search: string | null;
};

export type TransactionFormOptions = {
    types: EnumOption[];
    accounts: Account[];
    categories: Category[];
    funds: Fund[];
    defaultDocumentLocale: string;
};

/** One row of the income split preview. */
export type AllocationShare = {
    id: number;
    name: string;
    color: string;
    percentage: number;
    /** Sen. */
    amount: number;
};

/** Laravel's paginated resource envelope. */
export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export type DashboardPeriodValue =
    | 'this_month'
    | 'last_month'
    | 'this_year'
    | 'custom';

export type DashboardPeriod = {
    period: DashboardPeriodValue;
    from: string;
    to: string;
    /** Already translated: "vs last month", "vs last year", … */
    comparison_label: string;
};

/** The four stat cards. Amounts in sen, changes in percent. */
export type DashboardSummary = {
    income: number;
    expense: number;
    net: number;
    income_change: number | null;
    expense_change: number | null;
    net_change: number | null;
};

/** One bar in the 12-month chart. `month` is the first day of the month. */
export type MonthlyTotals = {
    month: string;
    /** Sen. */
    income: number;
    /** Sen. */
    expense: number;
};

/** One slice of the expense donut. `slot` picks the chart colour; -1 is "other". */
export type CategorySlice = {
    name: string;
    /** Sen. */
    amount: number;
    slot: number;
};

/** What the owner pay page suggests drawing, and how it got there. */
export type OwnerPaySuggestion = {
    /** Sen. */
    amount: number;
    /** Sen. */
    average_income: number;
    months: number;
    percentage: number;
};

/** Pre-filled fields for the transaction dialog. */
export type TransactionPrefill = {
    type?: TransactionTypeValue;
    /** Sen. */
    amount?: number;
    fund_id?: number | null;
    description?: string;
};

export type ProjectStatusValue =
    | 'prospect'
    | 'active'
    | 'completed'
    | 'on_hold'
    | 'cancelled';

export type QuotationStatusValue =
    | 'draft'
    | 'sent'
    | 'accepted'
    | 'rejected'
    | 'expired';

export type InvoiceStatusValue =
    | 'draft'
    | 'sent'
    | 'partial'
    | 'paid'
    | 'overdue'
    | 'void';

/** The badge tone the server picked for a status. */
export type StatusToneValue =
    | 'neutral'
    | 'income'
    | 'expense'
    | 'warning'
    | 'info'
    | 'primary';

/** What a client has been billed and what they still owe. All sen. */
export type ClientSummary = {
    invoiced: number;
    paid: number;
    outstanding: number;
    overdue: number;
    invoice_count: number;
};

export type Client = {
    id: number;
    name: string;
    company: string | null;
    display_name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    reg_no: string | null;
    notes: string | null;
    /** The language this client's documents are written in. */
    preferred_locale: string;
    preferred_locale_label: string;
    projects_count?: number;
    summary?: ClientSummary;
};

/** The money side of a project. All sen. */
export type ProjectSummary = {
    contract: number;
    invoiced: number;
    received: number;
    outstanding: number;
    costs: number;
    gross_profit: number;
};

export type Project = {
    id: number;
    client_id: number;
    client_name?: string;
    name: string;
    description: string | null;
    /** Sen. */
    contract_amount: number;
    status: ProjectStatusValue;
    status_label: string;
    status_tone: StatusToneValue;
    start_date: string | null;
    end_date: string | null;
    /** Sen. */
    internal_hourly_rate: number | null;
    summary?: ProjectSummary;
};

export type DocumentItem = {
    id: number;
    description: string;
    quantity: number;
    /** Sen. */
    unit_price: number;
    /** Sen. */
    amount: number;
    sort_order: number;
};

/** A line being edited, before the server prices it. */
export type DocumentItemInput = {
    description: string;
    quantity: number | string;
    /** Sen. */
    unit_price: number;
};

export type Quotation = {
    id: number;
    number: string;
    client_id: number;
    client_name?: string;
    project_id: number | null;
    project_name?: string | null;
    issue_date: string;
    valid_until: string;
    status: QuotationStatusValue;
    status_label: string;
    status_tone: StatusToneValue;
    is_editable: boolean;
    has_lapsed: boolean;
    /** The language the document itself is written in. */
    locale: string;
    /** Sen. */
    subtotal: number;
    /** Sen. */
    discount: number;
    /** Sen. */
    total: number;
    notes: string | null;
    terms: string | null;
    converted_invoice_id: number | null;
    converted_invoice_number?: string | null;
    items?: DocumentItem[];
};

export type Invoice = {
    id: number;
    number: string;
    client_id: number;
    client_name?: string;
    project_id: number | null;
    project_name?: string | null;
    quotation_id: number | null;
    quotation_number?: string | null;
    issue_date: string;
    due_date: string;
    status: InvoiceStatusValue;
    status_label: string;
    status_tone: StatusToneValue;
    is_editable: boolean;
    is_late: boolean;
    locale: string;
    /** Sen. */
    subtotal: number;
    /** Sen. */
    discount: number;
    /** Sen. */
    total: number;
    /** Sen. */
    paid_amount: number;
    /** Sen. */
    outstanding: number;
    notes: string | null;
    terms: string | null;
    sent_at: string | null;
    paid_at: string | null;
    items?: DocumentItem[];
    payments?: Transaction[];
};

/** What clients owe in total, for the dashboard card. */
export type OutstandingInvoices = {
    /** Sen. */
    amount: number;
    count: number;
    /** Sen. */
    overdue_amount: number;
    overdue_count: number;
};

export type RecurringKindValue = 'client_billing' | 'own_cost';
export type RecurringIntervalValue = 'monthly' | 'quarterly' | 'yearly';

export type RecurringItem = {
    id: number;
    kind: RecurringKindValue;
    kind_label: string;
    name: string;
    description: string | null;
    /** Sen. */
    amount: number;
    interval: RecurringIntervalValue;
    interval_label: string;
    next_date: string;
    /** The day of the month the charge belongs on, kept through clamping. */
    anchor_day: number;
    client_id: number | null;
    client_name?: string | null;
    project_id: number | null;
    project_name?: string | null;
    category_id: number | null;
    category_name?: string | null;
    account_id: number | null;
    account_name?: string | null;
    auto_generate: boolean;
    remind_days_before: number;
    is_active: boolean;
    /** Whether the warning window has opened. */
    is_due: boolean;
    reminds_on: string;
};

/** What the subscriptions are expected to do over the coming days. All sen. */
export type UpcomingRecurring = {
    income: number;
    expense: number;
    net: number;
    days: number;
    until: string;
};

/**
 * A row in the bell menu. It carries a translation key and its parameters,
 * never a finished sentence, so the text follows the reading language.
 */
export type AppNotification = {
    id: string;
    key: string;
    params: Record<string, string | number>;
    url: string | null;
    tone: string;
    read: boolean;
    created_at: string | null;
};

/** One row of the profit and loss breakdown. */
export type CategoryRow = {
    name: string;
    /** Sen. */
    amount: number;
    /** Percentage of that side's total. */
    share: number;
};

export type MonthRow = {
    month: string;
    /** Sen. */
    income: number;
    /** Sen. */
    expense: number;
    /** Sen. */
    net: number;
};

export type ProfitLossReport = {
    /** Sen. */
    income: number;
    /** Sen. */
    expense: number;
    /** Sen. */
    net: number;
    income_rows: CategoryRow[];
    expense_rows: CategoryRow[];
    months: MonthRow[];
};

export type CashFlowRow = {
    id: number;
    name: string;
    type_label: string;
    /** Sen. */
    opening: number;
    /** Sen. */
    in: number;
    /** Sen. */
    out: number;
    /** Sen. */
    net: number;
    /** Sen. */
    closing: number;
};

export type CashFlowReport = {
    rows: CashFlowRow[];
    totals: Omit<CashFlowRow, 'id' | 'name' | 'type_label'>;
};

export type TaxReport = {
    year: number;
    /** Sen. */
    income: number;
    /** Sen. */
    expense: number;
    /** Sen. */
    deductible: number;
    /** Sen. */
    non_deductible: number;
    /** Sen. Can be negative in a loss-making year. */
    taxable: number;
    /** Sen. What has been set aside in the tax fund. */
    tax_fund: number;
    deductible_rows: { name: string; amount: number }[];
    non_deductible_rows: { name: string; amount: number }[];
};

export type ProjectProfitRow = {
    id: number;
    name: string;
    client_name: string;
    status: ProjectStatusValue;
    status_label: string;
    status_tone: StatusToneValue;
    /** Sen. */
    contract: number;
    /** Sen. */
    invoiced: number;
    /** Sen. */
    received: number;
    /** Sen. */
    costs: number;
    minutes: number;
    /** Sen per hour. */
    hourly_rate: number;
    /** Sen. Logged minutes at the hourly rate. */
    labour: number;
    /** Sen. */
    profit: number;
    /** Null when no money has come in. */
    margin: number | null;
};

export type ForecastPoint = {
    date: string;
    /** Sen. */
    balance: number;
    /** Sen. */
    in: number;
    /** Sen. */
    out: number;
};

export type ForecastHorizon = ForecastPoint & { days: number };

export type ForecastReport = {
    /** Sen. The money on hand today. */
    opening: number;
    points: ForecastPoint[];
    horizons: ForecastHorizon[];
    /** Sen. */
    expected_in: number;
    /** Sen. */
    expected_out: number;
};

export type TimeEntry = {
    id: number;
    project_id: number;
    project_name?: string;
    started_at: string;
    ended_at: string | null;
    minutes: number | null;
    /** What a running timer has clocked up so far. */
    elapsed_minutes: number;
    is_running: boolean;
    note: string | null;
    billable: boolean;
};

/** The project figures with the logged time costed in. */
export type ProjectProfit = ProjectSummary & {
    minutes: number;
    minutes_total: number;
    /** Sen per hour. */
    hourly_rate: number;
    /** Sen. */
    labour: number;
    /** Sen. */
    profit_after_labour: number;
};
