<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { wTrans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import ReportShell from '@/components/app/ReportShell.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { formatNumber } from '@/lib/format';
import { formatHours } from '@/lib/duration';
import { formatMoney } from '@/lib/money';
import { show as showProject } from '@/routes/projects';
import { projects } from '@/routes/reports';
import type { DashboardPeriod, EnumOption, ProjectProfitRow } from '@/types';

const props = defineProps<{
    rows: ProjectProfitRow[];
    period: DashboardPeriod;
    periodOptions: EnumOption[];
    exportLocales: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'report.title', href: projects() }],
    },
});

const title = wTrans('report.project_profit');

const totals = computed(() =>
    props.rows.reduce(
        (sum, row) => ({
            received: sum.received + row.received,
            costs: sum.costs + row.costs,
            labour: sum.labour + row.labour,
            profit: sum.profit + row.profit,
            minutes: sum.minutes + row.minutes,
        }),
        { received: 0, costs: 0, labour: 0, profit: 0, minutes: 0 },
    ),
);
</script>

<template>
    <Head :title="title" />

    <ReportShell
        report="projects"
        :title="title"
        :description="$t('report.project_profit_description')"
        :period="period"
        :period-options="periodOptions"
        :export-locales="exportLocales"
        :show-period="false"
    >
        <p class="text-xs text-muted-foreground">
            {{ $t('report.project_profit_note') }}
        </p>

        <div class="overflow-x-auto rounded-xl border border-border bg-surface">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th
                            class="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('project.one') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('common.status') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('project.received') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('project.costs') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('report.hours') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('report.labour') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('report.profit') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('report.margin') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="border-b border-border last:border-0"
                    >
                        <td class="px-4 py-2.5">
                            <Link
                                :href="showProject(row.id)"
                                class="block min-w-0 space-y-0.5 hover:underline"
                            >
                                <span class="block truncate font-medium">
                                    {{ row.name }}
                                </span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                >
                                    {{ row.client_name }}
                                </span>
                            </Link>
                        </td>
                        <td class="px-4 py-2.5">
                            <StatusBadge
                                :label="row.status_label"
                                :tone="row.status_tone"
                            />
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatMoney(row.received) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatMoney(row.costs) }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right text-muted-foreground tabular-nums"
                        >
                            {{ formatHours(row.minutes) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatMoney(row.labour) }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right font-medium tabular-nums"
                            :class="
                                row.profit < 0 ? 'text-expense' : 'text-income'
                            "
                        >
                            {{ formatMoney(row.profit) }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right tabular-nums"
                            :class="
                                row.margin !== null && row.margin < 0
                                    ? 'text-expense'
                                    : ''
                            "
                        >
                            {{
                                row.margin === null
                                    ? '—'
                                    : `${formatNumber(row.margin)}%`
                            }}
                        </td>
                    </tr>

                    <tr v-if="rows.length === 0">
                        <td
                            colspan="8"
                            class="px-4 py-10 text-center text-sm text-muted-foreground"
                        >
                            {{ $t('project.empty_title') }}
                        </td>
                    </tr>

                    <tr
                        v-else
                        class="border-t border-foreground/70 font-medium"
                    >
                        <td class="px-4 py-2.5" colspan="2">
                            {{ $t('common.total') }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatMoney(totals.received) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatMoney(totals.costs) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatHours(totals.minutes) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatMoney(totals.labour) }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right tabular-nums"
                            :class="
                                totals.profit < 0
                                    ? 'text-expense'
                                    : 'text-income'
                            "
                        >
                            {{ formatMoney(totals.profit) }}
                        </td>
                        <td class="px-4 py-2.5"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ReportShell>
</template>
