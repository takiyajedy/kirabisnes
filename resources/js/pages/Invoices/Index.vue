<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Receipt } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/app/DataTable.vue';
import type { DataTableColumn } from '@/components/app/DataTable.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatCard from '@/components/app/StatCard.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { create, index, show } from '@/routes/invoices';
import type { Client, EnumOption, Invoice, Paginated } from '@/types';

const props = defineProps<{
    invoices: Paginated<Invoice>;
    filters: {
        status: string | null;
        client_id: number | null;
        search: string | null;
        outstanding: boolean;
    };
    statuses: EnumOption[];
    clients: Client[];
    totals: { outstanding: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'invoice.title', href: index() }],
    },
});

const title = wTrans('invoice.title');
const description = wTrans('invoice.description');

const ALL = '__all__';
const OUTSTANDING = '__outstanding__';

const filters = ref({
    search: props.filters.search ?? '',
    status: props.filters.outstanding
        ? OUTSTANDING
        : (props.filters.status ?? ALL),
    client_id: props.filters.client_id?.toString() ?? ALL,
});

const hasFilters = computed(
    () =>
        filters.value.search !== '' ||
        filters.value.status !== ALL ||
        filters.value.client_id !== ALL,
);

const apply = () => {
    const query: Record<string, string> = {};

    if (filters.value.search !== '') query.search = filters.value.search;
    if (filters.value.status === OUTSTANDING) {
        query.outstanding = '1';
    } else if (filters.value.status !== ALL) {
        query.status = filters.value.status;
    }
    if (filters.value.client_id !== ALL)
        query.client_id = filters.value.client_id;

    router.get(index().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => filters.value.search,
    () => {
        clearTimeout(timer);
        timer = setTimeout(apply, 300);
    },
);

watch([() => filters.value.status, () => filters.value.client_id], apply);

const columns = computed<DataTableColumn[]>(() => [
    { key: 'number', label: wTrans('invoice.number').value, class: 'w-40' },
    { key: 'client', label: wTrans('client.one').value },
    { key: 'due_date', label: wTrans('invoice.due_date').value, class: 'w-32' },
    { key: 'status', label: wTrans('common.status').value, class: 'w-32' },
    { key: 'total', label: wTrans('document.total').value, numeric: true },
    {
        key: 'outstanding',
        label: wTrans('invoice.outstanding').value,
        numeric: true,
    },
]);
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="title" :description="description">
            <template #actions>
                <Button
                    class="gap-1.5"
                    :disabled="clients.length === 0"
                    as-child
                >
                    <Link :href="create()">
                        <Plus class="size-4" />
                        {{ $t('invoice.new') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:max-w-xs">
            <StatCard
                :label="$t('invoice.total_outstanding')"
                :amount="totals.outstanding"
                :tone="totals.outstanding > 0 ? 'warning' : 'default'"
                :icon="Receipt"
            />
        </div>

        <div
            class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div class="grid gap-1.5 lg:col-span-2">
                <Label for="search" class="text-xs text-muted-foreground">
                    {{ $t('common.search') }}
                </Label>
                <Input
                    id="search"
                    v-model="filters.search"
                    type="search"
                    :placeholder="$t('invoice.search_placeholder')"
                />
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="filter-status"
                    class="text-xs text-muted-foreground"
                >
                    {{ $t('common.status') }}
                </Label>
                <Select v-model="filters.status">
                    <SelectTrigger id="filter-status">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">
                            {{ $t('document.filter.all_statuses') }}
                        </SelectItem>
                        <SelectItem :value="OUTSTANDING">
                            {{ $t('invoice.filter.outstanding') }}
                        </SelectItem>
                        <SelectItem
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="filter-client"
                    class="text-xs text-muted-foreground"
                >
                    {{ $t('client.one') }}
                </Label>
                <Select v-model="filters.client_id">
                    <SelectTrigger id="filter-client">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">
                            {{ $t('document.filter.all_clients') }}
                        </SelectItem>
                        <SelectItem
                            v-for="client in clients"
                            :key="client.id"
                            :value="client.id.toString()"
                        >
                            {{ client.display_name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <EmptyState
            v-if="invoices.data.length === 0 && !hasFilters"
            :title="$t('invoice.empty_title')"
            :description="
                clients.length === 0
                    ? $t('document.needs_client')
                    : $t('invoice.empty_description')
            "
            :icon="Receipt"
        >
            <template #action>
                <Button
                    class="gap-1.5"
                    :disabled="clients.length === 0"
                    as-child
                >
                    <Link :href="create()">
                        <Plus class="size-4" />
                        {{ $t('invoice.new') }}
                    </Link>
                </Button>
            </template>
        </EmptyState>

        <DataTable
            v-else
            :columns="columns"
            :rows="invoices.data"
            :meta="invoices.meta"
        >
            <template #[`cell:number`]="{ row }">
                <Link :href="show(row.id)" class="font-medium hover:underline">
                    {{ row.number }}
                </Link>
            </template>

            <template #[`cell:client`]="{ row }">
                <div class="min-w-0 space-y-0.5">
                    <p class="truncate text-sm">{{ row.client_name }}</p>
                    <p
                        v-if="row.project_name"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ row.project_name }}
                    </p>
                </div>
            </template>

            <template #[`cell:due_date`]="{ row }">
                <span
                    class="text-sm whitespace-nowrap"
                    :class="row.is_late ? 'text-expense' : ''"
                >
                    {{ formatDate(row.due_date) }}
                </span>
            </template>

            <template #[`cell:status`]="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>

            <template #[`cell:total`]="{ row }">
                <span class="whitespace-nowrap">
                    {{ formatMoney(row.total) }}
                </span>
            </template>

            <template #[`cell:outstanding`]="{ row }">
                <span
                    class="font-medium whitespace-nowrap"
                    :class="
                        row.outstanding > 0
                            ? 'text-warning'
                            : 'text-muted-foreground'
                    "
                >
                    {{ formatMoney(row.outstanding) }}
                </span>
            </template>
        </DataTable>
    </div>
</template>
