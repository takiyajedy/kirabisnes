import { onBeforeUnmount, onMounted, ref } from 'vue';

export type ChartTokens = {
    income: string;
    expense: string;
    border: string;
    mutedForeground: string;
    surface: string;
    neutral: string;
    palette: string[];
};

/**
 * Light-mode values, used while server-rendering and until the real tokens
 * can be read from the document.
 */
const FALLBACK: ChartTokens = {
    income: '#059669',
    expense: '#e11d48',
    border: '#e4e4e7',
    mutedForeground: '#71717a',
    surface: '#ffffff',
    neutral: '#a1a1aa',
    palette: ['#059669', '#0284c7', '#d97706', '#7c3aed', '#e11d48'],
};

/**
 * The chart colours, taken from the CSS custom properties so a chart is
 * never a second source of truth for the palette (PLAN.md section 4.1).
 *
 * Switching the theme swaps the `dark` class on the document root, which is
 * what this watches: that covers the toggle, a change in the operating
 * system's preference, and the class the server writes on first paint.
 */
export function useChartTheme() {
    const tokens = ref<ChartTokens>(FALLBACK);

    const read = () => {
        const styles = getComputedStyle(document.documentElement);
        const token = (name: string, fallback: string) =>
            styles.getPropertyValue(name).trim() || fallback;

        tokens.value = {
            income: token('--income', FALLBACK.income),
            expense: token('--expense', FALLBACK.expense),
            border: token('--border', FALLBACK.border),
            mutedForeground: token(
                '--muted-foreground',
                FALLBACK.mutedForeground,
            ),
            surface: token('--surface', FALLBACK.surface),
            neutral: token('--muted-foreground', FALLBACK.neutral),
            palette: [1, 2, 3, 4, 5].map((n, i) =>
                token(`--chart-${n}`, FALLBACK.palette[i]),
            ),
        };
    };

    let observer: MutationObserver | null = null;

    onMounted(() => {
        read();

        observer = new MutationObserver(read);
        observer.observe(document.documentElement, {
            attributeFilter: ['class'],
        });
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        observer = null;
    });

    return { tokens };
}
