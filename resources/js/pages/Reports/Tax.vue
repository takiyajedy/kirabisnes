<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Info, PiggyBank, Scale, TrendingDown, Wallet } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import ReportShell from '@/components/app/ReportShell.vue';
import ReportTable from '@/components/app/ReportTable.vue';
import type { ReportColumn } from '@/components/app/ReportTable.vue';
import StatCard from '@/components/app/StatCard.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatMoney } from '@/lib/money';
import { tax } from '@/routes/reports';
import type { DashboardPeriod, EnumOption, TaxReport } from '@/types';

const props = defineProps<{
    report: TaxReport;
    year: number;
    years: number[];
    period: DashboardPeriod;
    periodOptions: EnumOption[];
    exportLocales: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'report.title', href: tax() }],
    },
});

const title = wTrans('report.tax_summary');

// A tax summary is always a whole year, so it picks a year rather than a
// window.
const selectedYear = ref(props.year.toString());

watch(selectedYear, (value) => {
    router.get(
        tax().url,
        { year: value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
});

const columns = computed<ReportColumn[]>(() => [
    { label: wTrans('transaction.category').value },
    { label: wTrans('common.amount').value, numeric: true },
]);

const rowsFor = (rows: { name: string; amount: number }[], total: number) => [
    ...rows.map((row) => ({ cells: [row.name, formatMoney(row.amount)] })),
    {
        cells: [wTrans('common.total').value, formatMoney(total)],
        total: true,
    },
];
</script>

<template>
    <Head :title="title" />

    <ReportShell
        report="tax"
        :title="title"
        :description="$t('report.tax_description')"
        :period="period"
        :period-options="periodOptions"
        :export-locales="exportLocales"
        :show-period="false"
        :export-query="{ year }"
    >
        <template #filters>
            <div class="grid gap-1.5">
                <Label for="tax-year" class="text-xs text-muted-foreground">
                    {{ $t('report.year') }}
                </Label>
                <Select v-model="selectedYear">
                    <SelectTrigger id="tax-year" class="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in years"
                            :key="option"
                            :value="option.toString()"
                        >
                            {{ option }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </template>

        <!-- The warning the plan asks for, on the page as well as in the file -->
        <p
            class="flex items-start gap-2 rounded-xl border border-warning/40 bg-warning-muted px-4 py-3 text-sm text-warning"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            {{ $t('report.tax_note') }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :label="$t('report.total_income')"
                :amount="report.income"
                tone="income"
                :icon="Wallet"
            />
            <StatCard
                :label="$t('report.deductible')"
                :amount="report.deductible"
                tone="expense"
                :icon="TrendingDown"
                :hint="$t('report.deductible_hint')"
            />
            <StatCard
                :label="$t('report.taxable_estimate')"
                :amount="report.taxable"
                :tone="report.taxable < 0 ? 'expense' : 'default'"
                :icon="Scale"
            />
            <StatCard
                :label="$t('report.tax_fund')"
                :amount="report.tax_fund"
                :icon="PiggyBank"
                :hint="$t('report.tax_fund_hint')"
            />
        </div>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">
                {{ $t('report.deductible_by_category') }}
            </h2>
            <ReportTable
                :columns="columns"
                :rows="rowsFor(report.deductible_rows, report.deductible)"
                :empty-label="$t('report.no_rows')"
            />
        </section>

        <section class="space-y-3">
            <div class="space-y-1">
                <h2 class="text-sm font-medium">
                    {{ $t('report.non_deductible_by_category') }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ $t('report.non_deductible_hint') }}
                </p>
            </div>
            <ReportTable
                :columns="columns"
                :rows="
                    rowsFor(report.non_deductible_rows, report.non_deductible)
                "
                :empty-label="$t('report.no_rows')"
            />
        </section>
    </ReportShell>
</template>
