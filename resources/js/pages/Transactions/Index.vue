<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Paperclip,
    Pencil,
    Plus,
    Trash2,
    Upload,
} from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DataTable from '@/components/app/DataTable.vue';
import type { DataTableColumn } from '@/components/app/DataTable.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import TransactionFormDialog from '@/components/app/TransactionFormDialog.vue';
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
import { index as importIndex } from '@/routes/import';
import { destroy, index } from '@/routes/transactions';
import type {
    Paginated,
    Transaction,
    TransactionFilters,
    TransactionFormOptions,
} from '@/types';

const props = defineProps<{
    transactions: Paginated<Transaction>;
    filters: TransactionFilters;
    formOptions: TransactionFormOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'transaction.title', href: index() }],
    },
});

const title = wTrans('transaction.title');
const description = wTrans('transaction.description');

const ALL = '__all__';

// Filters live in the query string, so the view is shareable and the back
// button works (PLAN.md section 3.3).
const filters = ref({
    search: props.filters.search ?? '',
    type: props.filters.type ?? ALL,
    account_id: props.filters.account_id?.toString() ?? ALL,
    category_id: props.filters.category_id?.toString() ?? ALL,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const hasFilters = computed(
    () =>
        filters.value.search !== '' ||
        filters.value.type !== ALL ||
        filters.value.account_id !== ALL ||
        filters.value.category_id !== ALL ||
        filters.value.from !== '' ||
        filters.value.to !== '',
);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

const applyFilters = () => {
    const query: Record<string, string> = {};
    const { search, type, account_id, category_id, from, to } = filters.value;

    if (search !== '') query.search = search;
    if (type !== ALL) query.type = type;
    if (account_id !== ALL) query.account_id = account_id;
    if (category_id !== ALL) query.category_id = category_id;
    if (from !== '') query.from = from;
    if (to !== '') query.to = to;

    router.get(index().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Typing is debounced; the dropdowns and dates apply straight away.
watch(
    () => filters.value.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 300);
    },
);

watch(
    [
        () => filters.value.type,
        () => filters.value.account_id,
        () => filters.value.category_id,
        () => filters.value.from,
        () => filters.value.to,
    ],
    applyFilters,
);

const clearFilters = () => {
    filters.value = {
        search: '',
        type: ALL,
        account_id: ALL,
        category_id: ALL,
        from: '',
        to: '',
    };
};

const formOpen = ref(false);
const editing = ref<Transaction | null>(null);

const openCreate = () => {
    editing.value = null;
    formOpen.value = true;
};

const openEdit = (transaction: Transaction) => {
    editing.value = transaction;
    formOpen.value = true;
};

const remove = (transaction: Transaction) => {
    router.delete(destroy(transaction.id).url, { preserveScroll: true });
};

/** Money in is green and positive; money out is red and negative. */
const isInflow = (transaction: Transaction) =>
    transaction.type === 'income' || transaction.type === 'capital_injection';

const signedAmount = (transaction: Transaction) => {
    if (transaction.type === 'transfer') {
        return formatMoney(transaction.amount);
    }

    return isInflow(transaction)
        ? formatMoney(transaction.amount)
        : `−${formatMoney(transaction.amount)}`;
};

const amountTone = (transaction: Transaction) => {
    if (transaction.type === 'transfer') {
        return 'text-muted-foreground';
    }

    return isInflow(transaction) ? 'text-income' : 'text-expense';
};

const columns = computed<DataTableColumn[]>(() => [
    { key: 'date', label: wTrans('common.date').value, class: 'w-28' },
    { key: 'description', label: wTrans('common.description').value },
    { key: 'type', label: wTrans('common.type').value, class: 'w-40' },
    {
        key: 'account',
        label: wTrans('transaction.account').value,
        class: 'w-40',
    },
    { key: 'amount', label: wTrans('common.amount').value, numeric: true },
    { key: 'actions', label: '', class: 'w-24' },
]);
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="title" :description="description">
            <template #actions>
                <Button variant="secondary" class="gap-1.5" as-child>
                    <Link :href="importIndex()">
                        <Upload class="size-4" />
                        {{ $t('import.title') }}
                    </Link>
                </Button>
                <Button class="gap-1.5" @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('transaction.new') }}
                </Button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <div
            class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-6"
        >
            <div class="grid gap-1.5 lg:col-span-2">
                <Label for="search" class="text-xs text-muted-foreground">
                    {{ $t('common.search') }}
                </Label>
                <Input
                    id="search"
                    v-model="filters.search"
                    type="search"
                    :placeholder="$t('common.search_placeholder')"
                />
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-type" class="text-xs text-muted-foreground">
                    {{ $t('common.type') }}
                </Label>
                <Select v-model="filters.type">
                    <SelectTrigger id="filter-type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">
                            {{ $t('transaction.filter.all_types') }}
                        </SelectItem>
                        <SelectItem
                            v-for="option in formOptions.types"
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
                    for="filter-account"
                    class="text-xs text-muted-foreground"
                >
                    {{ $t('transaction.account') }}
                </Label>
                <Select v-model="filters.account_id">
                    <SelectTrigger id="filter-account">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">
                            {{ $t('transaction.filter.all_accounts') }}
                        </SelectItem>
                        <SelectItem
                            v-for="account in formOptions.accounts"
                            :key="account.id"
                            :value="account.id.toString()"
                        >
                            {{ account.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-from" class="text-xs text-muted-foreground">
                    {{ $t('transaction.filter.date_from') }}
                </Label>
                <Input id="filter-from" v-model="filters.from" type="date" />
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-to" class="text-xs text-muted-foreground">
                    {{ $t('transaction.filter.date_to') }}
                </Label>
                <Input id="filter-to" v-model="filters.to" type="date" />
            </div>

            <div class="grid gap-1.5 sm:col-span-2 lg:col-span-5">
                <Label
                    for="filter-category"
                    class="text-xs text-muted-foreground"
                >
                    {{ $t('transaction.category') }}
                </Label>
                <Select v-model="filters.category_id">
                    <SelectTrigger id="filter-category">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">
                            {{ $t('transaction.filter.all_categories') }}
                        </SelectItem>
                        <SelectItem
                            v-for="category in formOptions.categories"
                            :key="category.id"
                            :value="category.id.toString()"
                        >
                            {{ category.name }} · {{ category.type_label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-end">
                <Button
                    variant="ghost"
                    class="w-full"
                    :disabled="!hasFilters"
                    @click="clearFilters"
                >
                    {{ $t('transaction.filter.clear') }}
                </Button>
            </div>
        </div>

        <EmptyState
            v-if="transactions.data.length === 0 && !hasFilters"
            :title="$t('transaction.empty_title')"
            :description="$t('transaction.empty_description')"
            :icon="ArrowLeftRight"
        >
            <template #action>
                <Button class="gap-1.5" @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('transaction.new') }}
                </Button>
            </template>
        </EmptyState>

        <DataTable
            v-else
            :columns="columns"
            :rows="transactions.data"
            :meta="transactions.meta"
        >
            <template #[`cell:date`]="{ row }">
                <span class="text-sm whitespace-nowrap">
                    {{ formatDate(row.date) }}
                </span>
            </template>

            <template #[`cell:description`]="{ row }">
                <div class="min-w-0 space-y-0.5">
                    <p class="truncate font-medium">{{ row.description }}</p>
                    <p
                        v-if="
                            row.category_name || row.fund_name || row.reference
                        "
                        class="flex items-center gap-1.5 truncate text-xs text-muted-foreground"
                    >
                        <template v-if="row.category_name">
                            {{ row.category_name }}
                        </template>
                        <template v-if="row.fund_name">
                            · {{ row.fund_name }}
                        </template>
                        <template v-if="row.reference">
                            · {{ row.reference }}
                        </template>
                        <Paperclip v-if="row.has_receipt" class="size-3" />
                    </p>
                </div>
            </template>

            <template #[`cell:type`]="{ row }">
                <StatusBadge
                    :label="row.type_label"
                    :tone="
                        row.type === 'income'
                            ? 'income'
                            : row.type === 'transfer'
                              ? 'neutral'
                              : row.type === 'capital_injection'
                                ? 'info'
                                : 'expense'
                    "
                />
            </template>

            <template #[`cell:account`]="{ row }">
                <span class="truncate text-sm">
                    {{ row.account_name }}
                    <template v-if="row.to_account_name">
                        → {{ row.to_account_name }}
                    </template>
                </span>
            </template>

            <template #[`cell:amount`]="{ row }">
                <span
                    class="font-medium whitespace-nowrap"
                    :class="amountTone(row)"
                >
                    {{ signedAmount(row) }}
                </span>
            </template>

            <template #[`cell:actions`]="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="sm" @click="openEdit(row)">
                        <Pencil class="size-3.5" />
                        <span class="sr-only">{{ $t('common.edit') }}</span>
                    </Button>

                    <ConfirmDialog
                        :title="$t('transaction.delete_confirm')"
                        :description="$t('transaction.delete_description')"
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

    <TransactionFormDialog
        v-model:open="formOpen"
        :options="formOptions"
        :transaction="editing"
    />
</template>
