import type { ChartOptions, TooltipItem } from 'chart.js';
import { formatMoney } from '@/lib/money';

/**
 * Chart.js is loaded on demand, for two reasons: it draws onto a canvas, so
 * it has nothing to do during server-side rendering, and it is large enough
 * that pages without a chart should not pay for it.
 */
let registered = false;

export async function loadCharts() {
    const [chartjs, vueChartjs] = await Promise.all([
        import('chart.js'),
        import('vue-chartjs'),
    ]);

    if (!registered) {
        chartjs.Chart.register(
            chartjs.ArcElement,
            chartjs.BarController,
            chartjs.BarElement,
            chartjs.CategoryScale,
            chartjs.DoughnutController,
            chartjs.LineController,
            chartjs.LineElement,
            chartjs.LinearScale,
            chartjs.PointElement,
            chartjs.Legend,
            chartjs.Tooltip,
        );

        registered = true;
    }

    return vueChartjs;
}

/**
 * Amounts arrive in sen; the axis and the tooltips show ringgit.
 */
export function moneyTooltipLabel(
    item: TooltipItem<'bar' | 'doughnut' | 'line'>,
): string {
    const value = typeof item.raw === 'number' ? item.raw : 0;

    return `${item.dataset.label ?? item.label}: ${formatMoney(value)}`;
}

/**
 * The parts of the look that every chart in the app shares.
 */
export function baseOptions(
    font: string,
    mutedForeground: string,
): ChartOptions<'bar' | 'doughnut' | 'line'> {
    return {
        responsive: true,
        maintainAspectRatio: false,
        // The legends and tooltips should pick up a language change without
        // the chart being torn down, so the animation stays out of the way.
        animation: { duration: 250 },
        plugins: {
            legend: {
                labels: {
                    color: mutedForeground,
                    font: { family: font, size: 12 },
                    boxWidth: 10,
                    boxHeight: 10,
                    usePointStyle: true,
                    pointStyle: 'circle',
                },
            },
        },
    };
}
