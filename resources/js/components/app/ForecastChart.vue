<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { currentLocale, wTrans } from 'laravel-vue-i18n';
import { computed, defineAsyncComponent } from 'vue';
import { useChartTheme } from '@/composables/useChartTheme';
import { baseOptions, loadCharts, moneyTooltipLabel } from '@/lib/chart';
import { formatDate, formatNumber } from '@/lib/format';
import type { ForecastPoint } from '@/types';

const props = defineProps<{
    points: ForecastPoint[];
}>();

const Line = defineAsyncComponent(async () => (await loadCharts()).Line);

const { tokens } = useChartTheme();

const FONT = 'Inter, ui-sans-serif, system-ui, sans-serif';

const labels = computed(() => {
    // Reading the active language here is what redraws the dates when the
    // reader switches between BM and EN.
    void currentLocale.value;

    return props.points.map((point) => formatDate(point.date));
});

/** Red once the projection dips below zero, so the problem is visible. */
const goesNegative = computed(() =>
    props.points.some((point) => point.balance < 0),
);

const data = computed<ChartData<'line'>>(() => ({
    labels: labels.value,
    datasets: [
        {
            label: wTrans('report.projected_balance').value,
            data: props.points.map((point) => point.balance),
            borderColor: goesNegative.value
                ? tokens.value.expense
                : tokens.value.income,
            backgroundColor: goesNegative.value
                ? tokens.value.expense
                : tokens.value.income,
            borderWidth: 2,
            pointRadius: 0,
            pointHitRadius: 12,
            tension: 0.15,
            fill: false,
        },
    ],
}));

const options = computed<ChartOptions<'line'>>(() => {
    const base = baseOptions(FONT, tokens.value.mutedForeground);

    return {
        ...base,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            ...base.plugins,
            legend: { display: false },
            tooltip: { callbacks: { label: moneyTooltipLabel } },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { color: tokens.value.border },
                ticks: {
                    color: tokens.value.mutedForeground,
                    font: { family: FONT, size: 11 },
                    maxTicksLimit: 7,
                    autoSkip: true,
                },
            },
            y: {
                grid: { color: tokens.value.border },
                border: { display: false },
                ticks: {
                    color: tokens.value.mutedForeground,
                    font: { family: FONT, size: 11 },
                    maxTicksLimit: 6,
                    // Ringgit, not sen, and without the decimals.
                    callback: (value) =>
                        formatNumber(Number(value) / 100, {
                            maximumFractionDigits: 0,
                        }),
                },
            },
        },
    } as ChartOptions<'line'>;
});
</script>

<template>
    <div class="h-72">
        <Line :data="data" :options="options" />
    </div>
</template>
