<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { TrendingUp, TriangleAlert, Wallet } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import ClientOnly from '@/components/app/ClientOnly.vue';
import ForecastChart from '@/components/app/ForecastChart.vue';
import ReportShell from '@/components/app/ReportShell.vue';
import ReportTable from '@/components/app/ReportTable.vue';
import type { ReportColumn } from '@/components/app/ReportTable.vue';
import StatCard from '@/components/app/StatCard.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { forecast } from '@/routes/reports';
import type { DashboardPeriod, EnumOption, ForecastReport } from '@/types';

const props = defineProps<{
    report: ForecastReport;
    period: DashboardPeriod;
    periodOptions: EnumOption[];
    exportLocales: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'report.title', href: forecast() }],
    },
});

const title = wTrans('report.forecast');

const columns = computed<ReportColumn[]>(() => [
    { label: wTrans('report.horizon').value },
    { label: wTrans('common.date').value },
    { label: wTrans('report.expected_in').value, numeric: true },
    { label: wTrans('report.expected_out').value, numeric: true },
    { label: wTrans('report.projected_balance').value, numeric: true },
]);

const rows = computed(() =>
    props.report.horizons.map((horizon) => ({
        cells: [
            wTrans('report.in_days', { days: String(horizon.days) }).value,
            formatDate(horizon.date),
            formatMoney(horizon.in),
            formatMoney(horizon.out),
            formatMoney(horizon.balance),
        ],
        negative: horizon.balance < 0,
    })),
);

/** The first day the projection goes under, if it does. */
const dipsOn = computed(
    () => props.report.points.find((point) => point.balance < 0)?.date ?? null,
);
</script>

<template>
    <Head :title="title" />

    <ReportShell
        report="forecast"
        :title="title"
        :description="$t('report.forecast_description')"
        :period="period"
        :period-options="periodOptions"
        :export-locales="exportLocales"
        :show-period="false"
    >
        <div class="grid gap-4 md:grid-cols-3">
            <StatCard
                :label="$t('report.balance_today')"
                :amount="report.opening"
                :icon="Wallet"
            />
            <StatCard
                :label="$t('report.expected_in')"
                :amount="report.expected_in"
                tone="income"
                :icon="TrendingUp"
            />
            <StatCard
                :label="$t('report.expected_out')"
                :amount="report.expected_out"
                tone="expense"
            />
        </div>

        <p
            v-if="dipsOn !== null"
            class="flex items-start gap-2 rounded-xl border border-expense/40 bg-expense-muted px-4 py-3 text-sm text-expense"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            {{ $t('report.forecast_dips', { date: formatDate(dipsOn) }) }}
        </p>

        <section
            class="space-y-4 rounded-xl border border-border bg-surface p-5"
        >
            <div class="space-y-1">
                <h2 class="text-sm font-medium">
                    {{ $t('report.projected_balance') }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ $t('report.forecast_note') }}
                </p>
            </div>

            <ClientOnly>
                <ForecastChart :points="report.points" />
                <template #fallback>
                    <Skeleton class="h-72 w-full" />
                </template>
            </ClientOnly>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">{{ $t('report.horizons') }}</h2>
            <ReportTable
                :columns="columns"
                :rows="rows"
                :empty-label="$t('report.no_rows')"
            />
        </section>
    </ReportShell>
</template>
