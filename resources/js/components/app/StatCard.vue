<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import type { Component } from 'vue';
import { computed } from 'vue';
import { formatPercentChange } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    /** Amount in sen. Leave unset and pass `value` for non-money figures. */
    amount?: number;
    value?: string;
    /** Percentage change against the previous period. */
    change?: number | null;
    /** What the change is measured against. Defaults to "vs last month". */
    changeLabel?: string;
    /** A line under the figure, for explaining what it does and does not count. */
    hint?: string;
    icon?: Component;
    tone?: 'default' | 'income' | 'expense' | 'warning';
};

const props = withDefaults(defineProps<Props>(), {
    tone: 'default',
    change: null,
});

const displayValue = computed(() =>
    props.amount === undefined
        ? (props.value ?? '')
        : formatMoney(props.amount),
);

const valueTone = computed(
    () =>
        ({
            default: 'text-foreground',
            income: 'text-income',
            expense: 'text-expense',
            warning: 'text-warning',
        })[props.tone],
);

const isUp = computed(() => (props.change ?? 0) >= 0);
const comparisonLabel = computed(
    () => props.changeLabel ?? wTrans('stat.vs_last_month').value,
);
</script>

<template>
    <div class="rounded-xl border border-border bg-surface p-6">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm text-muted-foreground">{{ label }}</p>
            <component
                v-if="icon"
                :is="icon"
                class="size-[18px] shrink-0 text-muted-foreground"
            />
        </div>

        <p
            :class="
                cn(
                    'mt-2 text-3xl font-semibold tracking-tight tabular-nums',
                    valueTone,
                )
            "
        >
            {{ displayValue }}
        </p>

        <p
            v-if="change !== null"
            class="mt-2 flex items-center gap-1 text-xs text-muted-foreground"
        >
            <component
                :is="isUp ? ArrowUpRight : ArrowDownRight"
                :class="cn('size-3.5', isUp ? 'text-income' : 'text-expense')"
            />
            <span :class="isUp ? 'text-income' : 'text-expense'">
                {{ formatPercentChange(change) }}
            </span>
            <span>{{ comparisonLabel }}</span>
        </p>

        <p v-if="hint" class="mt-2 text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
