<script setup lang="ts">
import { cn } from '@/lib/utils';

/**
 * A plain report table: the first column is a label, the rest are figures.
 * Every report on screen uses it, so they all line up the same way.
 */
export type ReportColumn = {
    label: string;
    /** Right aligned with tabular numerals, like every other figure. */
    numeric?: boolean;
};

defineProps<{
    columns: ReportColumn[];
    rows: { cells: string[]; total?: boolean; negative?: boolean }[];
    emptyLabel: string;
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-border bg-surface">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border">
                    <th
                        v-for="(column, index) in columns"
                        :key="index"
                        :class="
                            cn(
                                'px-4 py-2.5 text-xs font-medium text-muted-foreground',
                                column.numeric ? 'text-right' : 'text-left',
                            )
                        "
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, rowIndex) in rows"
                    :key="rowIndex"
                    :class="
                        cn(
                            'border-b border-border last:border-0',
                            row.total && 'border-t-foreground/70 font-medium',
                        )
                    "
                >
                    <td
                        v-for="(cell, index) in row.cells"
                        :key="index"
                        :class="
                            cn(
                                'px-4 py-2.5',
                                columns[index]?.numeric &&
                                    'text-right tabular-nums',
                                row.negative &&
                                    index === row.cells.length - 1 &&
                                    'text-expense',
                            )
                        "
                    >
                        {{ cell }}
                    </td>
                </tr>

                <tr v-if="rows.length === 0">
                    <td
                        :colspan="columns.length"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        {{ emptyLabel }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
