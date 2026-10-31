<script setup lang="ts" generic="TRow extends Record<string, unknown>">
import { Link } from '@inertiajs/vue3';
import { wTrans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export type DataTableColumn = {
    key: string;
    label: string;
    /** Money and other figures are right aligned with tabular numerals. */
    numeric?: boolean;
    class?: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginationMeta = {
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type Props = {
    columns: DataTableColumn[];
    rows: TRow[];
    rowKey?: keyof TRow & string;
    meta?: PaginationMeta | null;
};

const props = withDefaults(defineProps<Props>(), {
    rowKey: 'id',
    meta: null,
});

const noResults = wTrans('common.no_results');

const summary = computed(() => {
    if (props.meta === null || props.meta.total === 0) {
        return null;
    }

    return {
        from: formatNumber(props.meta.from ?? 0),
        to: formatNumber(props.meta.to ?? 0),
        total: formatNumber(props.meta.total),
    };
});

const showingResults = computed(() =>
    summary.value === null
        ? ''
        : wTrans('common.showing_results', summary.value).value,
);
</script>

<template>
    <div class="space-y-4">
        <div class="overflow-hidden rounded-xl border border-border bg-surface">
            <Table>
                <TableHeader>
                    <TableRow class="hover:bg-transparent">
                        <TableHead
                            v-for="column in columns"
                            :key="column.key"
                            :class="
                                cn(
                                    'text-xs font-medium text-muted-foreground',
                                    column.numeric && 'text-right',
                                    column.class,
                                )
                            "
                        >
                            {{ column.label }}
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableRow
                        v-for="row in rows"
                        :key="String(row[rowKey])"
                        class="border-border"
                    >
                        <TableCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="
                                cn(
                                    column.numeric && 'text-right tabular-nums',
                                    column.class,
                                )
                            "
                        >
                            <slot
                                :name="`cell:${column.key}`"
                                :row="row"
                                :column="column"
                            >
                                {{ row[column.key] }}
                            </slot>
                        </TableCell>
                    </TableRow>

                    <TableRow
                        v-if="rows.length === 0"
                        class="hover:bg-transparent"
                    >
                        <TableCell
                            :colspan="columns.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            {{ noResults }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="meta && meta.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">{{ showingResults }}</p>

            <nav class="flex items-center gap-1">
                <template v-for="(link, index) in meta.links" :key="index">
                    <span
                        v-if="link.url === null"
                        class="px-2.5 py-1 text-xs text-muted-foreground/60"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        :class="
                            cn(
                                'rounded-lg px-2.5 py-1 text-xs transition-colors',
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )
                        "
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </div>
</template>
