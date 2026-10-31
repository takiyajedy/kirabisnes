<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Download,
    Eye,
    Pencil,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DocumentPreview from '@/components/app/DocumentPreview.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import { show as showClient } from '@/routes/clients';
import { show as showInvoice } from '@/routes/invoices';
import {
    convert,
    destroy,
    edit,
    index,
    pdf,
    status as statusRoute,
} from '@/routes/quotations';
import type { Client, EnumOption, Quotation } from '@/types';

const props = defineProps<{
    quotation: Quotation;
    client: Client;
    statuses: EnumOption[];
    pdfName: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'quotation.title', href: index() }],
    },
});

const setStatus = (value: string) => {
    if (value === props.quotation.status) {
        return;
    }

    router.patch(
        statusRoute(props.quotation.id).url,
        { status: value },
        { preserveScroll: true },
    );
};

const toInvoice = () => {
    router.post(convert(props.quotation.id).url);
};

const remove = () => {
    router.delete(destroy(props.quotation.id).url);
};
</script>

<template>
    <Head :title="quotation.number" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="quotation.number">
            <template #actions>
                <Button variant="ghost" size="sm" as-child>
                    <a
                        :href="pdf(quotation.id).url"
                        target="_blank"
                        rel="noopener"
                    >
                        <Eye class="size-4" />
                        {{ $t('document.preview') }}
                    </a>
                </Button>
                <Button variant="secondary" size="sm" as-child>
                    <a
                        :href="
                            pdf(quotation.id, { query: { download: 1 } }).url
                        "
                    >
                        <Download class="size-4" />
                        {{ $t('document.download') }}
                    </a>
                </Button>

                <Button
                    v-if="quotation.is_editable"
                    variant="secondary"
                    size="sm"
                    as-child
                >
                    <Link :href="edit(quotation.id)">
                        <Pencil class="size-4" />
                        {{ $t('common.edit') }}
                    </Link>
                </Button>

                <Button
                    v-if="quotation.converted_invoice_id === null"
                    size="sm"
                    class="gap-1.5"
                    @click="toInvoice"
                >
                    {{ $t('quotation.convert') }}
                    <ArrowRight class="size-4" />
                </Button>

                <ConfirmDialog
                    v-if="quotation.converted_invoice_id === null"
                    :title="$t('quotation.delete_confirm')"
                    :description="$t('quotation.delete_description')"
                    @confirm="remove"
                >
                    <template #trigger>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </template>
                </ConfirmDialog>
            </template>
        </PageHeader>

        <!-- Where it stands -->
        <section
            class="flex flex-wrap items-center gap-x-8 gap-y-4 rounded-xl border border-border bg-surface p-5"
        >
            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('client.one') }}
                </p>
                <Link
                    :href="showClient(client.id)"
                    class="font-medium underline-offset-4 hover:underline"
                >
                    {{ client.display_name }}
                </Link>
            </div>

            <div v-if="quotation.project_name" class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('project.one') }}
                </p>
                <p class="text-sm">{{ quotation.project_name }}</p>
            </div>

            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('quotation.issue_date') }}
                </p>
                <p class="text-sm">{{ formatDate(quotation.issue_date) }}</p>
            </div>

            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('quotation.valid_until') }}
                </p>
                <p
                    class="text-sm"
                    :class="quotation.has_lapsed ? 'text-warning' : ''"
                >
                    {{ formatDate(quotation.valid_until) }}
                </p>
            </div>

            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('document.language') }}
                </p>
                <p class="text-sm">{{ quotation.locale.toUpperCase() }}</p>
            </div>

            <div class="ml-auto space-y-1.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('common.status') }}
                </p>
                <Select
                    v-if="quotation.converted_invoice_id === null"
                    :model-value="quotation.status"
                    @update:model-value="setStatus(String($event))"
                >
                    <SelectTrigger class="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <StatusBadge
                    v-else
                    :label="quotation.status_label"
                    :tone="quotation.status_tone"
                />
            </div>
        </section>

        <p
            v-if="quotation.has_lapsed"
            class="flex items-center gap-2 rounded-xl border border-warning/40 bg-warning-muted px-4 py-3 text-sm text-warning"
        >
            <TriangleAlert class="size-4 shrink-0" />
            {{ $t('quotation.lapsed_warning') }}
        </p>

        <p
            v-if="quotation.converted_invoice_id !== null"
            class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-surface-muted px-4 py-3 text-sm"
        >
            <span>{{ $t('quotation.already_converted') }}</span>
            <Link
                :href="showInvoice(quotation.converted_invoice_id)"
                class="font-medium text-info underline-offset-4 hover:underline"
            >
                {{ quotation.converted_invoice_number }}
            </Link>
        </p>

        <DocumentPreview
            :items="quotation.items ?? []"
            :subtotal="quotation.subtotal"
            :discount="quotation.discount"
            :total="quotation.total"
            :notes="quotation.notes"
            :terms="quotation.terms"
        />
    </div>
</template>
