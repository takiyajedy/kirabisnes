<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Users } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DataTable from '@/components/app/DataTable.vue';
import type { DataTableColumn } from '@/components/app/DataTable.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/money';
import { destroy, index, show, store, update } from '@/routes/clients';
import type { Client, EnumOption, Paginated } from '@/types';

const props = defineProps<{
    clients: Paginated<Client>;
    filters: { search: string | null };
    locales: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'client.title', href: index() }],
    },
});

const title = wTrans('client.title');
const description = wTrans('client.description');

// --- search --------------------------------------------------------------
const search = ref(props.filters.search ?? '');
let timer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            index().url,
            search.value === '' ? {} : { search: search.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

// --- create and edit -----------------------------------------------------
const open = ref(false);
const editing = ref<Client | null>(null);

const form = useForm({
    name: '',
    company: '',
    email: '',
    phone: '',
    address: '',
    reg_no: '',
    notes: '',
    preferred_locale: 'ms',
});

const load = (client: Client | null) => {
    editing.value = client;
    form.defaults({
        name: client?.name ?? '',
        company: client?.company ?? '',
        email: client?.email ?? '',
        phone: client?.phone ?? '',
        address: client?.address ?? '',
        reg_no: client?.reg_no ?? '',
        notes: client?.notes ?? '',
        preferred_locale: client?.preferred_locale ?? 'ms',
    });
    form.reset();
    form.clearErrors();
    open.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (editing.value === null) {
        form.submit(store(), options);

        return;
    }

    form.submit(update(editing.value.id), options);
};

const remove = (client: Client) => {
    router.delete(destroy(client.id).url, { preserveScroll: true });
};

const columns = computed<DataTableColumn[]>(() => [
    { key: 'name', label: wTrans('common.name').value },
    { key: 'contact', label: wTrans('client.contact').value, class: 'w-56' },
    {
        key: 'locale',
        label: wTrans('client.document_language').value,
        class: 'w-32',
    },
    {
        key: 'outstanding',
        label: wTrans('invoice.outstanding').value,
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
                <Button class="gap-1.5" @click="load(null)">
                    <Plus class="size-4" />
                    {{ $t('client.new') }}
                </Button>
            </template>
        </PageHeader>

        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="grid max-w-md gap-1.5">
                <Label for="search" class="text-xs text-muted-foreground">
                    {{ $t('common.search') }}
                </Label>
                <Input
                    id="search"
                    v-model="search"
                    type="search"
                    :placeholder="$t('client.search_placeholder')"
                />
            </div>
        </div>

        <EmptyState
            v-if="clients.data.length === 0 && search === ''"
            :title="$t('client.empty_title')"
            :description="$t('client.empty_description')"
            :icon="Users"
        >
            <template #action>
                <Button class="gap-1.5" @click="load(null)">
                    <Plus class="size-4" />
                    {{ $t('client.new') }}
                </Button>
            </template>
        </EmptyState>

        <DataTable
            v-else
            :columns="columns"
            :rows="clients.data"
            :meta="clients.meta"
        >
            <template #[`cell:name`]="{ row }">
                <Link
                    :href="show(row.id)"
                    class="block min-w-0 space-y-0.5 hover:underline"
                >
                    <p class="truncate font-medium">{{ row.display_name }}</p>
                    <p
                        v-if="row.company && row.name !== row.company"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ row.name }}
                    </p>
                </Link>
            </template>

            <template #[`cell:contact`]="{ row }">
                <div class="min-w-0 space-y-0.5 text-xs text-muted-foreground">
                    <p v-if="row.email" class="truncate">{{ row.email }}</p>
                    <p v-if="row.phone" class="truncate">{{ row.phone }}</p>
                </div>
            </template>

            <template #[`cell:locale`]="{ row }">
                <StatusBadge
                    :label="row.preferred_locale_label"
                    tone="neutral"
                />
            </template>

            <template #[`cell:outstanding`]="{ row }">
                <div class="space-y-0.5">
                    <p
                        class="font-medium whitespace-nowrap"
                        :class="
                            (row.summary?.outstanding ?? 0) > 0
                                ? 'text-warning'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ formatMoney(row.summary?.outstanding ?? 0) }}
                    </p>
                    <p
                        v-if="(row.summary?.overdue ?? 0) > 0"
                        class="text-xs whitespace-nowrap text-expense"
                    >
                        {{ formatMoney(row.summary?.overdue ?? 0) }}
                        {{ $t('invoice.status.overdue') }}
                    </p>
                </div>
            </template>

            <template #[`cell:actions`]="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="sm" @click="load(row)">
                        <Pencil class="size-3.5" />
                        <span class="sr-only">{{ $t('common.edit') }}</span>
                    </Button>

                    <ConfirmDialog
                        :title="$t('client.delete_confirm')"
                        :description="$t('client.delete_description')"
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

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? $t('client.edit') : $t('client.new') }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('client.name') }}</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="company">
                        {{ $t('client.company') }}
                        <span class="text-muted-foreground">
                            ({{ $t('common.optional') }})
                        </span>
                    </Label>
                    <Input id="company" v-model="form.company" />
                    <InputError :message="form.errors.company" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="email">{{ $t('client.email') }}</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">{{ $t('client.phone') }}</Label>
                        <Input id="phone" v-model="form.phone" />
                        <InputError :message="form.errors.phone" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="address">{{ $t('client.address') }}</Label>
                    <Textarea id="address" v-model="form.address" rows="3" />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="reg_no">{{ $t('client.reg_no') }}</Label>
                        <Input id="reg_no" v-model="form.reg_no" />
                        <InputError :message="form.errors.reg_no" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="preferred_locale">
                            {{ $t('client.document_language') }}
                        </Label>
                        <Select v-model="form.preferred_locale">
                            <SelectTrigger id="preferred_locale">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in locales"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('client.document_language_hint') }}
                        </p>
                        <InputError :message="form.errors.preferred_locale" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="notes">{{ $t('common.notes') }}</Label>
                    <Textarea id="notes" v-model="form.notes" rows="2" />
                    <InputError :message="form.errors.notes" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ $t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ $t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
