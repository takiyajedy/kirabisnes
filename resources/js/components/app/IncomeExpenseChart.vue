<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { currentLocale, wTrans } from 'laravel-vue-i18n';
import { computed, defineAsyncComponent } from 'vue';
import { useChartTheme } from '@/composables/useChartTheme';
import { baseOptions, loadCharts, moneyTooltipLabel } from '@/lib/chart';
import { formatMonth, formatNumber } from '@/lib/format';
import type { MonthlyTotals } from '@/types';

const props = defineProps<{
    series: MonthlyTotals[];
}>();

const Bar = defineAsyncComponent(async () => (await loadCharts()).Bar);

const { tokens } = useChartTheme();

const FONT = 'Inter, ui-sans-serif, system-ui, sans-serif';

const labels = computed(() => {
    // Reading the active language here is what redraws the month names when
    // the reader switches between BM and EN.
    void currentLocale.value;

    return props.series.map((point) => formatMonth(point.month));
});

const data = computed<ChartData<'bar'>>(() => ({
    labels: labels.value,
    datasets: [
        {
            label: wTrans('dashboard.money_in').value,
            data: props.series.map((point) => point.income),
            backgroundColor: tokens.value.income,
            borderRadius: 4,
            maxBarThickness: 18,
        },
        {
            label: wTrans('dashboard.money_out').value,
            data: props.series.map((point) => point.expense),
            backgroundColor: tokens.value.expense,
            borderRadius: 4,
            maxBarThickness: 18,
        },
    ],
}));

const options = computed<ChartOptions<'bar'>>(() => {
    const base = baseOptions(FONT, tokens.value.mutedForeground);

    return {
        ...base,
        plugins: {
            ...base.plugins,
            tooltip: {
                callbacks: { label: moneyTooltipLabel },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { color: tokens.value.border },
                ticks: {
                    color: tokens.value.mutedForeground,
                    font: { family: FONT, size: 11 },
                },
            },
            y: {
                beginAtZero: true,
                grid: { color: tokens.value.border },
                border: { display: false },
                ticks: {
                    color: tokens.value.mutedForeground,
                    font: { family: FONT, size: 11 },
                    maxTicksLimit: 5,
                    // Ringgit, not sen, and without the decimals: an axis
                    // only needs the order of magnitude.
                    callback: (value) =>
                        formatNumber(Number(value) / 100, {
                            maximumFractionDigits: 0,
                        }),
                },
            },
        },
    } as ChartOptions<'bar'>;
});
</script>

<template>
    <div class="h-72">
        <Bar :data="data" :options="options" />
    </div>
</template>
