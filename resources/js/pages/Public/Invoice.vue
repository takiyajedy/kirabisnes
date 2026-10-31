<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download, Landmark, TriangleAlert } from '@lucide/vue';
import { loadLanguageAsync } from 'laravel-vue-i18n';
import { computed, onMounted } from 'vue';
import DocumentPreview from '@/components/app/DocumentPreview.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import type { DocumentItem, StatusToneValue } from '@/types';

/**
 * The invoice as a client sees it.
 *
 * It reads in the language the invoice was written in; the two links at the
 * top carry the same signature, so a client who would rather read the other
 * one can switch without losing access.
 */
const props = defineProps<{
    invoice: {
        number: string;
        issue_date: string;
        due_date: string;
        status: string;
        status_label: string;
        status_tone: StatusToneValue;
        is_late: boolean;
        subtotal: number;
        discount: number;
        total: number;
        paid_amount: number;
        outstanding: number;
        notes: string | null;
        terms: string | null;
        project_name: string | null;
        items: DocumentItem[];
    };
    client: {
        display_name: string;
        name: string;
        address: string | null;
        reg_no: string | null;
    };
    business: {
        name: string;
        reg_no: string;
        email: string;
        phone: string;
        address: string;
        bank_details: string;
    };
    links: { ms: string; en: string; pdf: string };
    locale: string;
}>();

// The server picked the language; the i18n plugin has to be told, because
// this page is not reached through the usual locale middleware chain.
onMounted(() => {
    void loadLanguageAsync(props.locale);
});

/** The visitor has no account, so the formatters are told the locale. */
const tag = computed(() => (props.locale === 'en' ? 'en-MY' : 'ms-MY'));

const extraTotals = computed(() =>
    props.invoice.paid_amount > 0
        ? [
              {
                  label: props.locale === 'en' ? 'Paid' : 'Dibayar',
                  value: `−${formatMoney(props.invoice.paid_amount, tag.value)}`,
              },
              {
                  label: props.locale === 'en' ? 'Outstanding' : 'Tertunggak',
                  value: formatMoney(props.invoice.outstanding, tag.value),
                  strong: true,
              },
          ]
        : [],
);
</script>

<template>
    <Head :title="invoice.number" />

    <div class="mx-auto flex min-h-svh max-w-3xl flex-col gap-6 p-4 md:p-8">
        <!-- Who it is from, and which language to read it in -->
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <p class="text-lg font-semibold tracking-tight">
                    {{ business.name }}
                </p>
                <p v-if="business.reg_no" class="text-xs text-muted-foreground">
                    {{ $t('pdf.reg_no') }}: {{ business.reg_no }}
                </p>
                <p
                    v-if="business.address"
                    class="text-xs whitespace-pre-line text-muted-foreground"
                >
                    {{ business.address }}
                </p>
                <p v-if="business.email" class="text-xs text-muted-foreground">
                    {{ business.email }}
                    <template v-if="business.phone">
                        · {{ business.phone }}
                    </template>
                </p>
            </div>

            <nav
                class="flex items-center gap-1 rounded-lg border border-border p-0.5"
            >
                <a
                    v-for="option in [
                        { value: 'ms', short: 'BM', href: links.ms },
                        { value: 'en', short: 'EN', href: links.en },
                    ]"
                    :key="option.value"
                    :href="option.href"
                    class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                    :class="
                        locale === option.value
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    {{ option.short }}
                </a>
            </nav>
        </header>

        <!-- The invoice itself -->
        <section
            class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-surface p-5"
        >
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ $t('invoice.one') }} {{ invoice.number }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ $t('pdf.billed_to') }}: {{ client.display_name }}
                    <template v-if="invoice.project_name">
                        · {{ invoice.project_name }}
                    </template>
                </p>
            </div>

            <div class="space-y-2 text-right">
                <StatusBadge
                    :label="invoice.status_label"
                    :tone="invoice.status_tone"
                />
                <p class="text-xs text-muted-foreground">
                    {{ $t('invoice.issue_date') }}:
                    {{ formatDate(invoice.issue_date, tag) }}
                </p>
                <p
                    class="text-xs"
                    :class="
                        invoice.is_late
                            ? 'text-expense'
                            : 'text-muted-foreground'
                    "
                >
                    {{ $t('invoice.due_date') }}:
                    {{ formatDate(invoice.due_date, tag) }}
                </p>
            </div>
        </section>

        <p
            v-if="invoice.is_late"
            class="flex items-center gap-2 rounded-xl border border-expense/40 bg-expense-muted px-4 py-3 text-sm text-expense"
        >
            <TriangleAlert class="size-4 shrink-0" />
            {{ $t('invoice.status.overdue') }}
        </p>

        <DocumentPreview
            :items="invoice.items"
            :subtotal="invoice.subtotal"
            :discount="invoice.discount"
            :total="invoice.total"
            :notes="invoice.notes"
            :terms="invoice.terms"
            :extra-totals="extraTotals"
        />

        <!-- How to pay -->
        <section
            v-if="business.bank_details && invoice.outstanding > 0"
            class="space-y-2 rounded-xl border border-border bg-surface-muted p-5"
        >
            <h2 class="flex items-center gap-1.5 text-sm font-medium">
                <Landmark class="size-4 text-muted-foreground" />
                {{ $t('pdf.payment_details') }}
            </h2>
            <p class="text-sm whitespace-pre-line">
                {{ business.bank_details }}
            </p>
            <p class="text-sm font-medium">
                {{ $t('invoice.outstanding') }}:
                {{ formatMoney(invoice.outstanding, tag) }}
            </p>
        </section>

        <div class="mt-auto flex flex-wrap items-center justify-between gap-3">
            <Button variant="secondary" class="gap-1.5" as-child>
                <a :href="links.pdf">
                    <Download class="size-4" />
                    {{ $t('document.download') }}
                </a>
            </Button>

            <p class="text-xs text-muted-foreground">
                {{ $t('public.footer', { business: business.name }) }}
            </p>
        </div>
    </div>
</template>
