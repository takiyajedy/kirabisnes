<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Download, FileText } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    cashFlow,
    // Wayfinder renames the export action: `export` is a reserved word in JS.
    exportMethod,
    forecast,
    profitLoss,
    projects,
    tax,
} from '@/routes/reports';
import type { DashboardPeriod, EnumOption } from '@/types';

/**
 * The frame every report sits in: the tab strip, the period picker, and the
 * export menu.
 *
 * The window lives in the query string like the other filters, so a report
 * can be bookmarked. The export language is its own choice rather than the
 * reading language: a report often goes to an accountant who reads the
 * other one (PLAN.md phase 5).
 */
const props = defineProps<{
    /** Which tab is open. */
    report: 'profit-loss' | 'cash-flow' | 'tax' | 'projects' | 'forecast';
    title: string;
    description?: string;
    period: DashboardPeriod;
    periodOptions: EnumOption[];
    exportLocales: EnumOption[];
    /** Reports that are not scoped to a window hide the picker. */
    showPeriod?: boolean;
    /** Extra query values the export has to carry, such as the tax year. */
    exportQuery?: Record<string, string | number>;
}>();

const tabs = computed(() => [
    { key: 'profit-loss', label: 'report.profit_loss', href: profitLoss() },
    { key: 'cash-flow', label: 'report.cash_flow', href: cashFlow() },
    { key: 'tax', label: 'report.tax_summary', href: tax() },
    { key: 'projects', label: 'report.project_profit', href: projects() },
    { key: 'forecast', label: 'report.forecast', href: forecast() },
]);

// --- period --------------------------------------------------------------
const selected = ref(props.period.period);
const from = ref(props.period.from);
const to = ref(props.period.to);

const isCustom = computed(() => selected.value === 'custom');

const apply = () => {
    const query: Record<string, string> = { period: selected.value };

    if (selected.value === 'custom') {
        query.from = from.value;
        query.to = to.value;
    }

    router.get(window.location.pathname, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(selected, (value) => {
    if (value === 'custom') {
        from.value = props.period.from;
        to.value = props.period.to;
    }

    apply();
});

watch([from, to], () => {
    if (isCustom.value && from.value !== '' && to.value !== '') {
        apply();
    }
});

// --- export --------------------------------------------------------------
const exportUrl = (format: 'csv' | 'pdf', locale: string) =>
    exportMethod.url(props.report, {
        query: {
            format,
            locale,
            period: props.period.period,
            from: props.period.from,
            to: props.period.to,
            ...props.exportQuery,
        },
    });
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="title" :description="description">
            <template #actions>
                <div class="flex flex-wrap items-end gap-2">
                    <template v-if="showPeriod !== false">
                        <div class="grid gap-1.5">
                            <Label
                                for="report-period"
                                class="text-xs text-muted-foreground"
                            >
                                {{ $t('dashboard.period.label') }}
                            </Label>
                            <Select v-model="selected">
                                <SelectTrigger id="report-period" class="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in periodOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <template v-if="isCustom">
                            <div class="grid gap-1.5">
                                <Label
                                    for="report-from"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ $t('common.from') }}
                                </Label>
                                <Input
                                    id="report-from"
                                    v-model="from"
                                    type="date"
                                    class="w-36"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    for="report-to"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ $t('common.to') }}
                                </Label>
                                <Input
                                    id="report-to"
                                    v-model="to"
                                    type="date"
                                    class="w-36"
                                />
                            </div>
                        </template>
                    </template>

                    <slot name="filters" />

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="secondary" class="gap-1.5">
                                <Download class="size-4" />
                                {{ $t('report.export') }}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ $t('report.export_language') }}
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <template
                                v-for="locale in exportLocales"
                                :key="locale.value"
                            >
                                <DropdownMenuItem as-child>
                                    <a :href="exportUrl('pdf', locale.value)">
                                        <FileText class="size-4" />
                                        PDF · {{ locale.label }}
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <a :href="exportUrl('csv', locale.value)">
                                        <Download class="size-4" />
                                        CSV · {{ locale.label }}
                                    </a>
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>
        </PageHeader>

        <!-- Which report -->
        <nav
            class="flex gap-1 overflow-x-auto rounded-xl border border-border bg-surface p-1"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                class="rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                :class="
                    report === tab.key
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                "
            >
                {{ $t(tab.label) }}
            </Link>
        </nav>

        <slot />
    </div>
</template>
