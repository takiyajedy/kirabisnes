<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FolderKanban, Pencil, Plus, Trash2 } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DataTable from '@/components/app/DataTable.vue';
import type { DataTableColumn } from '@/components/app/DataTable.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import ProjectFormDialog from '@/components/app/ProjectFormDialog.vue';
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
import { destroy, index, show } from '@/routes/projects';
import type { Client, EnumOption, Paginated, Project } from '@/types';

const props = defineProps<{
    projects: Paginated<Project>;
    filters: {
        search: string | null;
        status: string | null;
        client_id: number | null;
    };
    statuses: EnumOption[];
    clients: Client[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'project.title', href: index() }],
    },
});

const title = wTrans('project.title');
const description = wTrans('project.description');

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

// --- create and edit -----------------------------------------------------
const open = ref(false);
const editing = ref<Project | null>(null);

const load = (project: Project | null) => {
    editing.value = project;
    open.value = true;
};

const remove = (project: Project) => {
    router.delete(destroy(project.id).url, { preserveScroll: true });
};

const columns = computed<DataTableColumn[]>(() => [
    { key: 'name', label: wTrans('project.name').value },
    { key: 'client', label: wTrans('client.one').value, class: 'w-48' },
    { key: 'status', label: wTrans('common.status').value, class: 'w-32' },
    {
        key: 'contract',
        label: wTrans('project.contract_amount').value,
        numeric: true,
    },
    {
        key: 'profit',
        label: wTrans('project.gross_profit').value,
        numeric: true,
    },
    { key: 'actions', label: '', class: 'w-24' },
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
                    @click="load(null)"
                >
                    <Plus class="size-4" />
                    {{ $t('project.new') }}
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
                    :placeholder="$t('project.search_placeholder')"
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
                            {{ $t('project.filter.all_statuses') }}
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
                            {{ $t('project.filter.all_clients') }}
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
            v-if="projects.data.length === 0 && !hasFilters"
            :title="$t('project.empty_title')"
            :description="
                clients.length === 0
                    ? $t('project.needs_client')
                    : $t('project.empty_description')
            "
            :icon="FolderKanban"
        >
            <template #action>
                <Button
                    class="gap-1.5"
                    :disabled="clients.length === 0"
                    @click="load(null)"
                >
                    <Plus class="size-4" />
                    {{ $t('project.new') }}
                </Button>
            </template>
        </EmptyState>

        <DataTable
            v-else
            :columns="columns"
            :rows="projects.data"
            :meta="projects.meta"
        >
            <template #[`cell:name`]="{ row }">
                <Link
                    :href="show(row.id)"
                    class="block min-w-0 space-y-0.5 hover:underline"
                >
                    <p class="truncate font-medium">{{ row.name }}</p>
                    <p
                        v-if="row.start_date"
                        class="text-xs text-muted-foreground"
                    >
                        {{ formatDate(row.start_date) }}
                    </p>
                </Link>
            </template>

            <template #[`cell:client`]="{ row }">
                <span class="truncate text-sm text-muted-foreground">
                    {{ row.client_name }}
                </span>
            </template>

            <template #[`cell:status`]="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>

            <template #[`cell:contract`]="{ row }">
                <span class="whitespace-nowrap">
                    {{ formatMoney(row.contract_amount) }}
                </span>
            </template>

            <template #[`cell:profit`]="{ row }">
                <span
                    class="font-medium whitespace-nowrap"
                    :class="
                        (row.summary?.gross_profit ?? 0) < 0
                            ? 'text-expense'
                            : 'text-income'
                    "
                >
                    {{ formatMoney(row.summary?.gross_profit ?? 0) }}
                </span>
            </template>

            <template #[`cell:actions`]="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="sm" @click="load(row)">
                        <Pencil class="size-3.5" />
                        <span class="sr-only">{{ $t('common.edit') }}</span>
                    </Button>

                    <ConfirmDialog
                        :title="$t('project.delete_confirm')"
                        :description="$t('project.delete_description')"
                        @confirm="remove(row)"
                    >
                        <template #trigger>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 class="size-3.5" />
                                <span class="sr-only">
                                    {{ $t('common.delete') }}
                                </span>
                            </Button>
                        </template>
                    </ConfirmDialog>
                </div>
            </template>
        </DataTable>
    </div>

    <ProjectFormDialog
        v-model:open="open"
        :project="editing"
        :clients="clients"
        :statuses="statuses"
    />
</template>
