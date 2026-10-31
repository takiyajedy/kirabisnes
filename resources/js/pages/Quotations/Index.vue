<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/app/DataTable.vue';
import type { DataTableColumn } from '@/components/app/DataTable.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
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
import { create, index, show } from '@/routes/quotations';
import type { Client, EnumOption, Paginated, Quotation } from '@/types';

const props = defineProps<{
    quotations: Paginated<Quotation>;
    filters: {
        status: string | null;
        client_id: number | null;
        search: string | null;
    };
    statuses: EnumOption[];
    clients: Client[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'quotation.title', href: index() }],
    },
});

const title = wTrans('quotation.title');
const description = wTrans('quotation.description');

const ALL = '__all__';

const filters = ref({
    search: props.filters.search ?? '',
    status: props.filters.status ?? ALL,
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
    if (filters.value.status !== ALL) query.status = filters.value.status;
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
    { key: 'number', label: wTrans('quotation.number').value, class: 'w-40' },
    { key: 'client', label: wTrans('client.one').value },
    {
        key: 'valid_until',
        label: wTrans('quotation.valid_until').value,
        class: 'w-32',
    },
    { key: 'status', label: wTrans('common.status').value, class: 'w-32' },
    { key: 'total', label: wTrans('document.total').value, numeric: true },
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
                        {{ $t('quotation.new') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

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
                    :placeholder="$t('quotation.search_placeholder')"
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
            v-if="quotations.data.length === 0 && !hasFilters"
            :title="$t('quotation.empty_title')"
            :description="
                clients.length === 0
                    ? $t('document.needs_client')
                    : $t('quotation.empty_description')
            "
            :icon="FileText"
        >
            <template #action>
                <Button
                    class="gap-1.5"
                    :disabled="clients.length === 0"
                    as-child
                >
                    <Link :href="create()">
                        <Plus class="size-4" />
                        {{ $t('quotation.new') }}
                    </Link>
                </Button>
            </template>
        </EmptyState>

        <DataTable
            v-else
            :columns="columns"
            :rows="quotations.data"
            :meta="quotations.meta"
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

            <template #[`cell:valid_until`]="{ row }">
                <span
                    class="text-sm whitespace-nowrap"
                    :class="row.has_lapsed ? 'text-warning' : ''"
                >
                    {{ formatDate(row.valid_until) }}
                </span>
            </template>

            <template #[`cell:status`]="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>

            <template #[`cell:total`]="{ row }">
                <span class="font-medium whitespace-nowrap">
                    {{ formatMoney(row.total) }}
                </span>
            </template>
        </DataTable>
    </div>
</template>
