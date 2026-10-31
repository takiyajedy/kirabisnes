<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { computed, defineAsyncComponent } from 'vue';
import { useChartTheme } from '@/composables/useChartTheme';
import { baseOptions, loadCharts, moneyTooltipLabel } from '@/lib/chart';
import type { CategorySlice } from '@/types';

const props = defineProps<{
    slices: CategorySlice[];
}>();

const Doughnut = defineAsyncComponent(
    async () => (await loadCharts()).Doughnut,
);

const { tokens } = useChartTheme();

const FONT = 'Inter, ui-sans-serif, system-ui, sans-serif';

/**
 * The server sends each slice a slot; -1 is the "everything else" slice and
 * takes the neutral colour.
 */
const colorFor = (slot: number) =>
    slot < 0
        ? tokens.value.neutral
        : tokens.value.palette[slot % tokens.value.palette.length];

const data = computed<ChartData<'doughnut'>>(() => ({
    // The names are already resolved in the reader's language by the server.
    labels: props.slices.map((slice) => slice.name),
    datasets: [
        {
            data: props.slices.map((slice) => slice.amount),
            backgroundColor: props.slices.map((slice) => colorFor(slice.slot)),
            // A ring of the surface colour separates the slices in both themes.
            borderColor: tokens.value.surface,
            borderWidth: 2,
        },
    ],
}));

const options = computed<ChartOptions<'doughnut'>>(() => {
    const base = baseOptions(FONT, tokens.value.mutedForeground);

    return {
        ...base,
        cutout: '62%',
        plugins: {
            ...base.plugins,
            legend: { ...base.plugins?.legend, position: 'bottom' },
            tooltip: {
                callbacks: { label: moneyTooltipLabel },
            },
        },
    } as ChartOptions<'doughnut'>;
});
</script>

<template>
    <div class="h-72">
        <Doughnut :data="data" :options="options" />
    </div>
</template>
