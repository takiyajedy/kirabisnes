<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { wTrans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';
import DocumentItemsEditor from '@/components/app/DocumentItemsEditor.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { asRinggit, toRinggit } from '@/lib/money';
import { index, store, update } from '@/routes/invoices';
import type {
    Client,
    DocumentItemInput,
    EnumOption,
    Invoice,
    Project,
} from '@/types';

const props = defineProps<{
    invoice: Invoice | null;
    nextNumber: string;
    preset: { client_id: number | null; project_id: number | null };
    clients: Client[];
    projects: Project[];
    statuses: EnumOption[];
    locales: EnumOption[];
    defaults: { locale: string; payment_terms_days: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'invoice.title', href: index() }],
    },
});

const isEditing = computed(() => props.invoice !== null);

const today = () => new Date().toISOString().slice(0, 10);

/** The due date follows the payment terms from the business settings. */
const dueFrom = (issueDate: string) => {
    const date = new Date(issueDate);

    if (Number.isNaN(date.getTime())) {
        return issueDate;
    }

    date.setDate(date.getDate() + props.defaults.payment_terms_days);

    return date.toISOString().slice(0, 10);
};

const NONE = '__none__';

const startingClient =
    props.invoice?.client_id ??
    props.preset.client_id ??
    props.clients[0]?.id ??
    0;

const localeFor = (clientId: number) =>
    props.clients.find((client) => client.id === clientId)?.preferred_locale ??
    props.defaults.locale;

const form = useForm({
    client_id: startingClient,
    project_id: props.invoice?.project_id ?? props.preset.project_id ?? null,
    issue_date: props.invoice?.issue_date ?? today(),
    due_date: props.invoice?.due_date ?? dueFrom(today()),
    status: props.invoice?.status ?? 'draft',
    locale: props.invoice?.locale ?? localeFor(startingClient),
    discount: props.invoice?.discount ?? 0,
    notes: props.invoice?.notes ?? '',
    terms: props.invoice?.terms ?? '',
    items: (props.invoice?.items?.map((item) => ({
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
    })) ?? [
        { description: '', quantity: 1, unit_price: 0 },
    ]) as DocumentItemInput[],
});

form.transform((data) => ({
    ...asRinggit(data, 'discount'),
    project_id: data.project_id,
    items: data.items.map((item) => ({
        ...item,
        unit_price: toRinggit(item.unit_price),
    })),
}));

// Moving the issue date moves the due date with it, unless the owner has
// already set one of their own.
watch(
    () => form.issue_date,
    (issueDate, previous) => {
        if (previous !== undefined && form.due_date === dueFrom(previous)) {
            form.due_date = dueFrom(issueDate);
        }
    },
);

const availableProjects = computed(() =>
    props.projects.filter((project) => project.client_id === form.client_id),
);

const projectValue = computed({
    get: () => (form.project_id === null ? NONE : form.project_id.toString()),
    set: (value: string) => {
        form.project_id = value === NONE ? null : Number(value);
    },
});

const onClientChange = () => {
    if (!isEditing.value) {
        form.locale = localeFor(form.client_id);
    }

    if (
        form.project_id !== null &&
        !availableProjects.value.some((p) => p.id === form.project_id)
    ) {
        form.project_id = null;
    }
};

const submit = () => {
    if (props.invoice !== null) {
        form.submit(update(props.invoice.id));

        return;
    }

    form.submit(store());
};

const title = computed(() =>
    isEditing.value
        ? wTrans('invoice.edit').value
        : wTrans('invoice.new').value,
);
</script>

<template>
    <Head :title="title" />

    <form
        class="flex flex-1 flex-col gap-6 p-4 md:p-6"
        @submit.prevent="submit"
    >
        <PageHeader :title="title" :description="nextNumber">
            <template #actions>
                <Button type="submit" :disabled="form.processing">
                    {{ $t('common.save') }}
                </Button>
            </template>
        </PageHeader>

        <section
            class="grid gap-4 rounded-xl border border-border bg-surface p-5 sm:grid-cols-2 lg:grid-cols-3"
        >
            <div class="grid gap-2">
                <Label for="client_id">{{ $t('client.one') }}</Label>
                <Select
                    v-model="form.client_id"
                    @update:model-value="onClientChange"
                >
                    <SelectTrigger id="client_id">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="client in clients"
                            :key="client.id"
                            :value="client.id"
                        >
                            {{ client.display_name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.client_id" />
            </div>

            <div class="grid gap-2">
                <Label for="project_id">
                    {{ $t('project.one') }}
                    <span class="text-muted-foreground">
                        ({{ $t('common.optional') }})
                    </span>
                </Label>
                <Select v-model="projectValue">
                    <SelectTrigger id="project_id">
                        <SelectValue :placeholder="$t('common.none')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">
                            {{ $t('common.none') }}
                        </SelectItem>
                        <SelectItem
                            v-for="project in availableProjects"
                            :key="project.id"
                            :value="project.id.toString()"
                        >
                            {{ project.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.project_id" />
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{ $t('document.language') }}</Label>
                <Select v-model="form.locale">
                    <SelectTrigger id="locale">
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
                    {{ $t('document.language_hint') }}
                </p>
                <InputError :message="form.errors.locale" />
            </div>

            <div class="grid gap-2">
                <Label for="issue_date">{{ $t('invoice.issue_date') }}</Label>
                <Input
                    id="issue_date"
                    v-model="form.issue_date"
                    type="date"
                    required
                />
                <InputError :message="form.errors.issue_date" />
            </div>

            <div class="grid gap-2">
                <Label for="due_date">{{ $t('invoice.due_date') }}</Label>
                <Input
                    id="due_date"
                    v-model="form.due_date"
                    type="date"
                    required
                />
                <p class="text-xs text-muted-foreground">
                    {{
                        $t('invoice.terms_hint', {
                            days: String(defaults.payment_terms_days),
                        })
                    }}
                </p>
                <InputError :message="form.errors.due_date" />
            </div>

            <div class="grid gap-2">
                <Label for="status">{{ $t('common.status') }}</Label>
                <Select v-model="form.status">
                    <SelectTrigger id="status">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in statuses.filter(
                                (option) =>
                                    option.value === 'draft' ||
                                    option.value === 'sent',
                            )"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    {{ $t('invoice.status_hint') }}
                </p>
                <InputError :message="form.errors.status" />
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">{{ $t('document.items') }}</h2>
            <DocumentItemsEditor
                v-model:items="form.items"
                v-model:discount="form.discount"
                :errors="form.errors as Record<string, string>"
            />
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="notes">{{ $t('document.notes') }}</Label>
                <Textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    :placeholder="$t('document.notes_placeholder')"
                />
                <InputError :message="form.errors.notes" />
            </div>
            <div class="grid gap-2">
                <Label for="terms">{{ $t('document.terms') }}</Label>
                <Textarea
                    id="terms"
                    v-model="form.terms"
                    rows="3"
                    :placeholder="$t('document.terms_placeholder')"
                />
                <InputError :message="form.errors.terms" />
            </div>
        </section>
    </form>
</template>
