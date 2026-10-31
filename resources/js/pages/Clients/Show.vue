<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    FolderKanban,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Receipt,
} from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import DocumentList from '@/components/app/DocumentList.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatCard from '@/components/app/StatCard.vue';
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
import { index, update } from '@/routes/clients';
import { create as createInvoice } from '@/routes/invoices';
import { show as showProject } from '@/routes/projects';
import { create as createQuotation } from '@/routes/quotations';
import type { Client, EnumOption, Invoice, Project, Quotation } from '@/types';

const props = defineProps<{
    client: Client;
    projects: Project[];
    invoices: Invoice[];
    quotations: Quotation[];
    locales: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'client.title', href: index() }],
    },
});

const open = ref(false);

const form = useForm({
    name: props.client.name,
    company: props.client.company ?? '',
    email: props.client.email ?? '',
    phone: props.client.phone ?? '',
    address: props.client.address ?? '',
    reg_no: props.client.reg_no ?? '',
    notes: props.client.notes ?? '',
    preferred_locale: props.client.preferred_locale,
});

const submit = () => {
    form.submit(update(props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};

const projectsLabel = wTrans('project.title');
</script>

<template>
    <Head :title="client.display_name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="client.display_name"
            :description="
                client.company && client.name !== client.company
                    ? client.name
                    : undefined
            "
        >
            <template #actions>
                <StatusBadge
                    :label="client.preferred_locale_label"
                    tone="neutral"
                />
                <Button
                    variant="secondary"
                    class="gap-1.5"
                    @click="open = true"
                >
                    <Pencil class="size-4" />
                    {{ $t('common.edit') }}
                </Button>
                <Button class="gap-1.5" as-child>
                    <Link
                        :href="
                            createInvoice({ query: { client_id: client.id } })
                        "
                    >
                        <Plus class="size-4" />
                        {{ $t('invoice.new') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Figures -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :label="$t('client.total_invoiced')"
                :amount="client.summary?.invoiced ?? 0"
                :icon="Receipt"
            />
            <StatCard
                :label="$t('client.total_paid')"
                :amount="client.summary?.paid ?? 0"
                tone="income"
            />
            <StatCard
                :label="$t('invoice.outstanding')"
                :amount="client.summary?.outstanding ?? 0"
                :tone="
                    (client.summary?.outstanding ?? 0) > 0
                        ? 'warning'
                        : 'default'
                "
            />
            <StatCard
                :label="$t('invoice.status.overdue')"
                :amount="client.summary?.overdue ?? 0"
                :tone="
                    (client.summary?.overdue ?? 0) > 0 ? 'expense' : 'default'
                "
            />
        </div>

        <!-- Contact details -->
        <section
            class="grid gap-4 rounded-xl border border-border bg-surface p-5 sm:grid-cols-3"
        >
            <div v-if="client.email" class="flex items-start gap-2.5">
                <Mail class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <a
                    :href="`mailto:${client.email}`"
                    class="text-sm break-all underline-offset-4 hover:underline"
                >
                    {{ client.email }}
                </a>
            </div>
            <div v-if="client.phone" class="flex items-start gap-2.5">
                <Phone class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <span class="text-sm">{{ client.phone }}</span>
            </div>
            <div v-if="client.address" class="flex items-start gap-2.5">
                <MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <span class="text-sm whitespace-pre-line">
                    {{ client.address }}
                </span>
            </div>
            <p
                v-if="client.notes"
                class="text-sm whitespace-pre-line text-muted-foreground sm:col-span-3"
            >
                {{ client.notes }}
            </p>
        </section>

        <!-- Projects -->
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-sm font-medium">{{ projectsLabel }}</h2>
            </div>

            <EmptyState
                v-if="projects.length === 0"
                :title="$t('project.empty_title')"
                :icon="FolderKanban"
            />

            <ul
                v-else
                class="divide-y divide-border rounded-xl border border-border bg-surface"
            >
                <li v-for="project in projects" :key="project.id">
                    <Link
                        :href="showProject(project.id)"
                        class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-accent"
                    >
                        <div class="min-w-0 space-y-0.5">
                            <p class="truncate text-sm font-medium">
                                {{ project.name }}
                            </p>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ $t('project.contract_amount') }}:
                                {{ formatMoney(project.contract_amount) }}
                            </p>
                        </div>
                        <StatusBadge
                            :label="project.status_label"
                            :tone="project.status_tone"
                        />
                    </Link>
                </li>
            </ul>
        </section>

        <!-- Documents -->
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-sm font-medium">
                        {{ $t('invoice.title') }}
                    </h2>
                    <Button variant="ghost" size="sm" as-child>
                        <Link
                            :href="
                                createInvoice({
                                    query: { client_id: client.id },
                                })
                            "
                        >
                            {{ $t('invoice.new') }}
                        </Link>
                    </Button>
                </div>
                <DocumentList
                    :documents="invoices"
                    kind="invoice"
                    :empty-title="$t('invoice.empty_title')"
                />
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-sm font-medium">
                        {{ $t('quotation.title') }}
                    </h2>
                    <Button variant="ghost" size="sm" as-child>
                        <Link
                            :href="
                                createQuotation({
                                    query: { client_id: client.id },
                                })
                            "
                        >
                            {{ $t('quotation.new') }}
                        </Link>
                    </Button>
                </div>
                <DocumentList
                    :documents="quotations"
                    kind="quotation"
                    :empty-title="$t('quotation.empty_title')"
                />
            </section>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('client.edit') }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('client.name') }}</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="company">{{ $t('client.company') }}</Label>
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
