<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/app/EmptyState.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { show as showInvoice } from '@/routes/invoices';
import { show as showQuotation } from '@/routes/quotations';
import type { Invoice, Quotation } from '@/types';

/**
 * A compact list of invoices or quotations, for the client and project pages.
 * Both documents carry the same few fields worth showing in a list.
 */
const props = defineProps<{
    documents: (Invoice | Quotation)[];
    kind: 'invoice' | 'quotation';
    emptyTitle: string;
}>();

const href = (document: Invoice | Quotation) =>
    props.kind === 'invoice'
        ? showInvoice(document.id)
        : showQuotation(document.id);

/** Only an invoice has an amount still owed. */
const outstanding = (document: Invoice | Quotation) =>
    'outstanding' in document ? document.outstanding : 0;
</script>

<template>
    <EmptyState v-if="documents.length === 0" :title="emptyTitle" />

    <ul
        v-else
        class="divide-y divide-border rounded-xl border border-border bg-surface"
    >
        <li v-for="document in documents" :key="document.id">
            <Link
                :href="href(document)"
                class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-accent"
            >
                <div class="min-w-0 space-y-0.5">
                    <p class="truncate text-sm font-medium">
                        {{ document.number }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(document.issue_date) }}
                        <template v-if="document.project_name">
                            · {{ document.project_name }}
                        </template>
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <div class="space-y-0.5 text-right">
                        <p class="text-sm font-medium tabular-nums">
                            {{ formatMoney(document.total) }}
                        </p>
                        <p
                            v-if="outstanding(document) > 0"
                            class="text-xs text-warning tabular-nums"
                        >
                            {{ formatMoney(outstanding(document)) }}
                            {{ $t('invoice.still_owed') }}
                        </p>
                    </div>
                    <StatusBadge
                        :label="document.status_label"
                        :tone="document.status_tone"
                    />
                </div>
            </Link>
        </li>
    </ul>
</template>
