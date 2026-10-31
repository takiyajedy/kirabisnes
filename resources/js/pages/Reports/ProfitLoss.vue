<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, Scale } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import ReportShell from '@/components/app/ReportShell.vue';
import ReportTable from '@/components/app/ReportTable.vue';
import type { ReportColumn } from '@/components/app/ReportTable.vue';
import StatCard from '@/components/app/StatCard.vue';
import { formatMonth, formatNumber } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { profitLoss } from '@/routes/reports';
import type { DashboardPeriod, EnumOption, ProfitLossReport } from '@/types';

const props = defineProps<{
    report: ProfitLossReport;
    period: DashboardPeriod;
    periodOptions: EnumOption[];
    exportLocales: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'report.title', href: profitLoss() }],
    },
});

const title = wTrans('report.profit_loss');

const categoryColumns = computed<ReportColumn[]>(() => [
    { label: wTrans('transaction.category').value },
    { label: wTrans('common.amount').value, numeric: true },
    { label: wTrans('report.share').value, numeric: true },
]);

const monthColumns = computed<ReportColumn[]>(() => [
    { label: wTrans('report.month').value },
    { label: wTrans('dashboard.money_in').value, numeric: true },
    { label: wTrans('dashboard.money_out').value, numeric: true },
    { label: wTrans('dashboard.net_profit').value, numeric: true },
]);

const categoryRows = (rows: ProfitLossReport['income_rows'], total: number) => [
    ...rows.map((row) => ({
        cells: [
            row.name,
            formatMoney(row.amount),
            `${formatNumber(row.share)}%`,
        ],
    })),
    {
        cells: [wTrans('common.total').value, formatMoney(total), '100%'],
        total: true,
    },
];

const monthRows = computed(() => [
    ...props.report.months.map((month) => ({
        cells: [
            formatMonth(month.month),
            formatMoney(month.income),
            formatMoney(month.expense),
            formatMoney(month.net),
        ],
        negative: month.net < 0,
    })),
    {
        cells: [
            wTrans('common.total').value,
            formatMoney(props.report.income),
            formatMoney(props.report.expense),
            formatMoney(props.report.net),
        ],
        total: true,
        negative: props.report.net < 0,
    },
]);

const emptyLabel = wTrans('report.no_rows');
</script>

<template>
    <Head :title="title" />

    <ReportShell
        report="profit-loss"
        :title="title"
        :description="$t('report.profit_loss_description')"
        :period="period"
        :period-options="periodOptions"
        :export-locales="exportLocales"
    >
        <div class="grid gap-4 md:grid-cols-3">
            <StatCard
                :label="$t('dashboard.money_in')"
                :amount="report.income"
                tone="income"
                :icon="ArrowDownLeft"
            />
            <StatCard
                :label="$t('dashboard.money_out')"
                :amount="report.expense"
                tone="expense"
                :icon="ArrowUpRight"
            />
            <StatCard
                :label="$t('dashboard.net_profit')"
                :amount="report.net"
                :tone="report.net < 0 ? 'expense' : 'default'"
                :icon="Scale"
                :hint="$t('report.profit_note')"
            />
        </div>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">
                {{ $t('report.income_by_category') }}
            </h2>
            <ReportTable
                :columns="categoryColumns"
                :rows="categoryRows(report.income_rows, report.income)"
                :empty-label="emptyLabel"
            />
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">
                {{ $t('report.expense_by_category') }}
            </h2>
            <ReportTable
                :columns="categoryColumns"
                :rows="categoryRows(report.expense_rows, report.expense)"
                :empty-label="emptyLabel"
            />
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">{{ $t('report.by_month') }}</h2>
            <ReportTable
                :columns="monthColumns"
                :rows="monthRows"
                :empty-label="emptyLabel"
            />
        </section>
    </ReportShell>
</template>
