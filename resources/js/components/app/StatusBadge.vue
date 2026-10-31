<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

export type StatusTone =
    | 'neutral'
    | 'income'
    | 'expense'
    | 'warning'
    | 'info'
    | 'primary';

type Props = {
    label: string;
    tone?: StatusTone;
};

const props = withDefaults(defineProps<Props>(), {
    tone: 'neutral',
});

/** Soft background, no heavy border — Section 4.3 of PLAN.md. */
const toneClasses = computed(
    () =>
        ({
            neutral: 'bg-surface-muted text-muted-foreground',
            primary: 'bg-income-muted text-income',
            income: 'bg-income-muted text-income',
            expense: 'bg-expense-muted text-expense',
            warning: 'bg-warning-muted text-warning',
            info: 'bg-info-muted text-info',
        })[props.tone],
);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                toneClasses,
            )
        "
    >
        {{ label }}
    </span>
</template>
