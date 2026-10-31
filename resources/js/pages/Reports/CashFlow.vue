<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { wTrans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import ReportShell from '@/components/app/ReportShell.vue';
import ReportTable from '@/components/app/ReportTable.vue';
import type { ReportColumn } from '@/components/app/ReportTable.vue';
import StatCard from '@/components/app/StatCard.vue';
import { formatMoney } from '@/lib/money';
import { cashFlow } from '@/routes/reports';
import type { CashFlowReport, DashboardPeriod, EnumOption } from '@/types';

const props = defineProps<{
    report: CashFlowReport;
    period: DashboardPeriod;
    periodOptions: EnumOption[];
    exportLocales: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'report.title', href: cashFlow() }],
    },
});

const title = wTrans('report.cash_flow');

const columns = computed<ReportColumn[]>(() => [
    { label: wTrans('transaction.account').value },
    { label: wTrans('report.opening_balance').value, numeric: true },
    { label: wTrans('report.money_in').value, numeric: true },
    { label: wTrans('report.money_out').value, numeric: true },
    { label: wTrans('report.closing_balance').value, numeric: true },
]);

const rows = computed(() => [
    ...props.report.rows.map((row) => ({
        cells: [
            row.name,
            formatMoney(row.opening),
            formatMoney(row.in),
            formatMoney(row.out),
            formatMoney(row.closing),
        ],
        negative: row.closing < 0,
    })),
    {
        cells: [
            wTrans('common.total').value,
            formatMoney(props.report.totals.opening),
            formatMoney(props.report.totals.in),
            formatMoney(props.report.totals.out),
            formatMoney(props.report.totals.closing),
        ],
        total: true,
        negative: props.report.totals.closing < 0,
    },
]);
</script>

<template>
    <Head :title="title" />

    <ReportShell
        report="cash-flow"
        :title="title"
        :description="$t('report.cash_flow_description')"
        :period="period"
        :period-options="periodOptions"
        :export-locales="exportLocales"
    >
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :label="$t('report.opening_balance')"
                :amount="report.totals.opening"
            />
            <StatCard
                :label="$t('report.money_in')"
                :amount="report.totals.in"
                tone="income"
            />
            <StatCard
                :label="$t('report.money_out')"
                :amount="report.totals.out"
                tone="expense"
            />
            <StatCard
                :label="$t('report.closing_balance')"
                :amount="report.totals.closing"
                :tone="report.totals.closing < 0 ? 'expense' : 'default'"
            />
        </div>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">{{ $t('report.by_account') }}</h2>
            <ReportTable
                :columns="columns"
                :rows="rows"
                :empty-label="$t('report.no_rows')"
            />
        </section>

        <p class="text-xs text-muted-foreground">
            {{ $t('report.cash_flow_note') }}
        </p>
    </ReportShell>
</template>
