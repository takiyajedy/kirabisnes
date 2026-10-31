<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    ChartPie,
    PiggyBank,
    Receipt,
    Repeat,
    Scale,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import ClientOnly from '@/components/app/ClientOnly.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import ExpenseCategoryChart from '@/components/app/ExpenseCategoryChart.vue';
import IncomeExpenseChart from '@/components/app/IncomeExpenseChart.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatCard from '@/components/app/StatCard.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate, formatNumber } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { dashboard } from '@/routes';
import { index as ownerPayIndex } from '@/routes/owner-pay';
import { index as invoicesIndex } from '@/routes/invoices';
import { index as recurringIndex } from '@/routes/recurring';
import { index as transactionsIndex } from '@/routes/transactions';
import type {
    CategorySlice,
    DashboardPeriod,
    DashboardSummary,
    EnumOption,
    Fund,
    Invoice,
    MonthlyTotals,
    OutstandingInvoices,
    RecurringItem,
    Transaction,
    UpcomingRecurring,
} from '@/types';

const props = defineProps<{
    period: DashboardPeriod;
    periodOptions: EnumOption[];
    summary: DashboardSummary;
    totalBalance: number;
    funds: Fund[];
    monthly: MonthlyTotals[];
    expensesByCategory: CategorySlice[];
    recent: Transaction[];
    outstandingInvoices: OutstandingInvoices;
    invoicesDueSoon: Invoice[];
    upcomingRecurring: UpcomingRecurring;
    upcomingRecurringItems: RecurringItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'dashboard.breadcrumb', href: dashboard() }],
    },
});

const page = usePage();

const head = wTrans('dashboard.head');
const welcome = computed(
    () =>
        wTrans('dashboard.welcome', { name: page.props.auth.user.name }).value,
);

// --- period filter -------------------------------------------------------
// The window lives in the query string, so a dashboard can be bookmarked.
const selected = ref(props.period.period);
const from = ref(props.period.from);
const to = ref(props.period.to);

const isCustom = computed(() => selected.value === 'custom');

const apply = () => {
    const query: Record<string, string> = { period: selected.value };

    if (selected.value === 'custom') {
        query.from = from.value;
        query.to = to.value;
    }

    router.get(dashboard().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(selected, (value) => {
    // Switching to a custom range keeps the dates the server last resolved,
    // so the first load of the range is not an empty window.
    if (value === 'custom') {
        from.value = props.period.from;
        to.value = props.period.to;
    }

    apply();
});

watch([from, to], () => {
    if (isCustom.value && from.value !== '' && to.value !== '') {
        apply();
    }
});

// --- funds ---------------------------------------------------------------
const fundShare = (fund: Fund) => {
    const total = props.funds.reduce(
        (sum, f) => sum + Math.max(0, f.balance ?? 0),
        0,
    );

    if (total === 0) {
        return 0;
    }

    return (Math.max(0, fund.balance ?? 0) / total) * 100;
};

const hasExpenses = computed(() => props.expensesByCategory.length > 0);
const hasMonthly = computed(() =>
    props.monthly.some((point) => point.income > 0 || point.expense > 0),
);

/** Money in is green and positive; money out is red and negative. */
const isInflow = (transaction: Transaction) =>
    transaction.type === 'income' || transaction.type === 'capital_injection';

const signedAmount = (transaction: Transaction) =>
    transaction.type === 'transfer' || isInflow(transaction)
        ? formatMoney(transaction.amount)
        : `−${formatMoney(transaction.amount)}`;

const amountTone = (transaction: Transaction) => {
    if (transaction.type === 'transfer') {
        return 'text-muted-foreground';
    }

    return isInflow(transaction) ? 'text-income' : 'text-expense';
};
</script>

<template>
    <Head :title="head" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="head" :description="welcome">
            <template #actions>
                <div class="flex flex-wrap items-end gap-2">
                    <div class="grid gap-1.5">
                        <Label
                            for="period"
                            class="text-xs text-muted-foreground"
                        >
                            {{ $t('dashboard.period.label') }}
                        </Label>
                        <Select v-model="selected">
                            <SelectTrigger id="period" class="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in periodOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <template v-if="isCustom">
                        <div class="grid gap-1.5">
                            <Label
                                for="period-from"
                                class="text-xs text-muted-foreground"
                            >
                                {{ $t('common.from') }}
                            </Label>
                            <Input
                                id="period-from"
                                v-model="from"
                                type="date"
                                class="w-40"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="period-to"
                                class="text-xs text-muted-foreground"
                            >
                                {{ $t('common.to') }}
                            </Label>
                            <Input
                                id="period-to"
                                v-model="to"
                                type="date"
                                class="w-40"
                            />
                        </div>
                    </template>
                </div>
            </template>
        </PageHeader>

        <!-- Stat cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :label="$t('dashboard.money_in')"
                :amount="summary.income"
                :change="summary.income_change"
                :change-label="period.comparison_label"
                tone="income"
                :icon="ArrowDownLeft"
            />
            <StatCard
                :label="$t('dashboard.money_out')"
                :amount="summary.expense"
                :change="summary.expense_change"
                :change-label="period.comparison_label"
                tone="expense"
                :icon="ArrowUpRight"
            />
            <StatCard
                :label="$t('dashboard.net_profit')"
                :amount="summary.net"
                :change="summary.net_change"
                :change-label="period.comparison_label"
                :tone="summary.net < 0 ? 'expense' : 'default'"
                :icon="Scale"
                :hint="$t('dashboard.net_profit_hint')"
            />
            <StatCard
                :label="$t('dashboard.total_balance')"
                :amount="totalBalance"
                :icon="Wallet"
                :hint="$t('dashboard.total_balance_hint')"
            />
        </div>

        <!-- Money clients still owe -->
        <section class="grid gap-4 lg:grid-cols-3">
            <StatCard
                :label="$t('dashboard.outstanding_invoices')"
                :amount="outstandingInvoices.amount"
                :tone="outstandingInvoices.amount > 0 ? 'warning' : 'default'"
                :icon="Receipt"
                :hint="
                    $t('dashboard.outstanding_invoices_hint', {
                        count: String(outstandingInvoices.count),
                        overdue: String(outstandingInvoices.overdue_count),
                    })
                "
            />

            <div
                class="space-y-1 rounded-xl border border-border bg-surface p-5 lg:col-span-2"
            >
                <div class="flex items-center justify-between gap-4 pb-2">
                    <h2 class="text-sm font-medium">
                        {{ $t('dashboard.invoices_due_soon') }}
                    </h2>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="invoicesIndex()">
                            {{ $t('dashboard.see_all') }}
                        </Link>
                    </Button>
                </div>

                <EmptyState
                    v-if="invoicesDueSoon.length === 0"
                    :title="$t('dashboard.nothing_owed')"
                    :icon="Receipt"
                />

                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="invoice in invoicesDueSoon"
                        :key="invoice.id"
                        class="flex items-center justify-between gap-4 py-2.5"
                    >
                        <div class="min-w-0 space-y-0.5">
                            <p class="truncate text-sm font-medium">
                                {{ invoice.number }} · {{ invoice.client_name }}
                            </p>
                            <p
                                class="text-xs"
                                :class="
                                    invoice.is_late
                                        ? 'text-expense'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ $t('invoice.due_date') }}:
                                {{ formatDate(invoice.due_date) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-sm font-medium tabular-nums">
                                {{ formatMoney(invoice.outstanding) }}
                            </span>
                            <StatusBadge
                                :label="invoice.status_label"
                                :tone="invoice.status_tone"
                            />
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Fund balances -->
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-sm font-medium">
                    {{ $t('dashboard.fund_balances') }}
                </h2>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="ownerPayIndex()">
                        {{ $t('dashboard.go_to_owner_pay') }}
                    </Link>
                </Button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="fund in funds"
                    :key="fund.id"
                    class="space-y-3 rounded-xl border border-border bg-surface p-4"
                    :class="
                        (fund.balance ?? 0) < 0
                            ? 'border-expense/40 bg-expense-muted'
                            : ''
                    "
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: fund.color }"
                            />
                            <p class="truncate text-sm font-medium">
                                {{ fund.name }}
                            </p>
                        </div>
                        <StatusBadge
                            v-if="!fund.is_active"
                            :label="$t('fund.inactive')"
                            tone="neutral"
                        />
                    </div>

                    <p
                        class="text-xl font-semibold tracking-tight tabular-nums"
                        :class="(fund.balance ?? 0) < 0 ? 'text-expense' : ''"
                    >
                        {{ formatMoney(fund.balance ?? 0) }}
                    </p>

                    <div class="space-y-1.5">
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-surface-muted"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: `${fundShare(fund)}%`,
                                    backgroundColor: fund.color,
                                }"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ formatNumber(fund.percentage) }}%
                            {{ $t('fund.of_income') }}
                        </p>
                    </div>

                    <p
                        v-if="(fund.balance ?? 0) < 0"
                        class="flex items-center gap-1.5 text-xs text-expense"
                    >
                        <TriangleAlert class="size-3.5 shrink-0" />
                        {{ $t('fund.negative_warning') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- What the subscriptions are expected to do next month -->
        <section class="grid gap-4 lg:grid-cols-3">
            <div
                class="space-y-4 rounded-xl border border-border bg-surface p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            {{
                                $t('dashboard.next_days', {
                                    days: String(upcomingRecurring.days),
                                })
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('dashboard.next_days_hint') }}
                        </p>
                    </div>
                    <Repeat
                        class="size-[18px] shrink-0 text-muted-foreground"
                    />
                </div>

                <dl class="space-y-2">
                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <dt class="text-muted-foreground">
                            {{ $t('recurring.expected_in') }}
                        </dt>
                        <dd class="font-medium text-income tabular-nums">
                            {{ formatMoney(upcomingRecurring.income) }}
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <dt class="text-muted-foreground">
                            {{ $t('recurring.expected_out') }}
                        </dt>
                        <dd class="font-medium text-expense tabular-nums">
                            {{ formatMoney(upcomingRecurring.expense) }}
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-4 border-t border-border pt-2"
                    >
                        <dt class="text-sm font-medium">
                            {{ $t('dashboard.net_expected') }}
                        </dt>
                        <dd
                            class="text-lg font-semibold tracking-tight tabular-nums"
                            :class="
                                upcomingRecurring.net < 0
                                    ? 'text-expense'
                                    : 'text-income'
                            "
                        >
                            {{ formatMoney(upcomingRecurring.net) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="space-y-1 rounded-xl border border-border bg-surface p-5 lg:col-span-2"
            >
                <div class="flex items-center justify-between gap-4 pb-2">
                    <h2 class="text-sm font-medium">
                        {{ $t('recurring.coming_up') }}
                    </h2>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="recurringIndex()">
                            {{ $t('dashboard.see_all') }}
                        </Link>
                    </Button>
                </div>

                <EmptyState
                    v-if="upcomingRecurringItems.length === 0"
                    :title="$t('recurring.none_coming_up')"
                    :icon="Repeat"
                />

                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="item in upcomingRecurringItems"
                        :key="item.id"
                        class="flex items-center justify-between gap-4 py-2.5"
                    >
                        <div class="min-w-0 space-y-0.5">
                            <p class="truncate text-sm font-medium">
                                {{ item.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDate(item.next_date) }} ·
                                {{ item.interval_label }}
                                <template v-if="item.client_name">
                                    · {{ item.client_name }}
                                </template>
                            </p>
                        </div>
                        <span
                            class="text-sm font-medium whitespace-nowrap tabular-nums"
                            :class="
                                item.kind === 'client_billing'
                                    ? 'text-income'
                                    : 'text-expense'
                            "
                        >
                            {{ item.kind === 'client_billing' ? '' : '−'
                            }}{{ formatMoney(item.amount) }}
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Charts -->
        <div class="grid gap-4 lg:grid-cols-5">
            <section
                class="space-y-4 rounded-xl border border-border bg-surface p-5 lg:col-span-3"
            >
                <div class="space-y-1">
                    <h2 class="text-sm font-medium">
                        {{ $t('dashboard.chart.in_out') }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ $t('dashboard.chart.in_out_description') }}
                    </p>
                </div>

                <EmptyState
                    v-if="!hasMonthly"
                    :title="$t('dashboard.chart.empty')"
                    :icon="ChartPie"
                />
                <ClientOnly v-else>
                    <IncomeExpenseChart :series="monthly" />
                    <template #fallback>
                        <Skeleton class="h-72 w-full" />
                    </template>
                </ClientOnly>
            </section>

            <section
                class="space-y-4 rounded-xl border border-border bg-surface p-5 lg:col-span-2"
            >
                <div class="space-y-1">
                    <h2 class="text-sm font-medium">
                        {{ $t('dashboard.chart.by_category') }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ $t('dashboard.chart.by_category_description') }}
                    </p>
                </div>

                <EmptyState
                    v-if="!hasExpenses"
                    :title="$t('dashboard.chart.empty')"
                    :icon="ChartPie"
                />
                <ClientOnly v-else>
                    <ExpenseCategoryChart :slices="expensesByCategory" />
                    <template #fallback>
                        <Skeleton class="h-72 w-full" />
                    </template>
                </ClientOnly>
            </section>
        </div>

        <!-- Recent transactions -->
        <section
            class="space-y-1 rounded-xl border border-border bg-surface p-5"
        >
            <div class="flex items-center justify-between gap-4 pb-3">
                <h2 class="text-sm font-medium">
                    {{ $t('dashboard.recent') }}
                </h2>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="transactionsIndex()">
                        {{ $t('dashboard.see_all') }}
                    </Link>
                </Button>
            </div>

            <EmptyState
                v-if="recent.length === 0"
                :title="$t('transaction.empty_title')"
                :description="$t('transaction.empty_description')"
                :icon="PiggyBank"
            />

            <ul v-else class="divide-y divide-border">
                <li
                    v-for="transaction in recent"
                    :key="transaction.id"
                    class="flex items-center justify-between gap-4 py-3"
                >
                    <div class="min-w-0 space-y-0.5">
                        <p class="truncate text-sm font-medium">
                            {{ transaction.description }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDate(transaction.date) }} ·
                            {{ transaction.type_label }}
                            <template v-if="transaction.category_name">
                                · {{ transaction.category_name }}
                            </template>
                        </p>
                    </div>

                    <span
                        class="text-sm font-medium whitespace-nowrap"
                        :class="amountTone(transaction)"
                    >
                        {{ signedAmount(transaction) }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
