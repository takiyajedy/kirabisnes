<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Ban,
    Copy,
    Download,
    Eye,
    FileText,
    Link2,
    Mail,
    Pencil,
    Plus,
    Send,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DocumentPreview from '@/components/app/DocumentPreview.vue';
import MoneyInput from '@/components/app/MoneyInput.vue';
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
import { formatDate } from '@/lib/format';
import { asRinggit, formatMoney } from '@/lib/money';
import { show as showClient } from '@/routes/clients';
import {
    destroy,
    edit,
    index,
    markSent,
    pay,
    pdf,
    send,
    // Wayfinder renames the void action: `void` is a reserved word in JS.
    voidMethod as voidInvoice,
} from '@/routes/invoices';
import { destroy as removePayment } from '@/routes/invoices/payments';
import { show as showQuotation } from '@/routes/quotations';
import type { Account, Category, Client, Invoice, Transaction } from '@/types';

const props = defineProps<{
    invoice: Invoice;
    client: Client;
    accounts: Account[];
    incomeCategories: Category[];
    pdfName: string;
    canEmail: boolean;
    publicLink: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'invoice.title', href: index() }],
    },
});

const NONE = '__none__';

// --- recording a payment -------------------------------------------------
const payOpen = ref(false);

const payForm = useForm({
    account_id: props.accounts[0]?.id ?? 0,
    amount: props.invoice.outstanding,
    date: new Date().toISOString().slice(0, 10),
    reference: '',
    category_id: null as number | null,
});

// The form holds sen; the server is given ringgit.
payForm.transform((data) => asRinggit(data, 'amount'));

const categoryValue = computed({
    get: () =>
        payForm.category_id === null ? NONE : payForm.category_id.toString(),
    set: (value: string) => {
        payForm.category_id = value === NONE ? null : Number(value);
    },
});

const openPay = () => {
    payForm.defaults({
        account_id: props.accounts[0]?.id ?? 0,
        amount: props.invoice.outstanding,
        date: new Date().toISOString().slice(0, 10),
        reference: '',
        category_id: null,
    });
    payForm.reset();
    payForm.clearErrors();
    payOpen.value = true;
};

const submitPay = () => {
    payForm.submit(pay(props.invoice.id), {
        preserveScroll: true,
        onSuccess: () => {
            payOpen.value = false;
        },
    });
};

const dropPayment = (payment: Transaction) => {
    router.delete(removePayment([props.invoice.id, payment.id]).url, {
        preserveScroll: true,
    });
};

// --- sending and voiding -------------------------------------------------
const sendEmail = () => {
    router.post(send(props.invoice.id).url, {}, { preserveScroll: true });
};

const markAsSent = () => {
    router.post(markSent(props.invoice.id).url, {}, { preserveScroll: true });
};

const makeVoid = () => {
    router.post(
        voidInvoice(props.invoice.id).url,
        {},
        { preserveScroll: true },
    );
};

const remove = () => {
    router.delete(destroy(props.invoice.id).url);
};

// Copying the link is the point of showing it; the tick is the feedback.
const copied = ref(false);

const copyLink = async () => {
    if (props.publicLink === null) {
        return;
    }

    await navigator.clipboard.writeText(props.publicLink);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const isVoid = computed(() => props.invoice.status === 'void');
const isPaid = computed(() => props.invoice.status === 'paid');

const extraTotals = computed(() =>
    props.invoice.paid_amount > 0
        ? [
              {
                  label: wTrans('invoice.paid_amount').value,
                  value: `−${formatMoney(props.invoice.paid_amount)}`,
              },
              {
                  label: wTrans('invoice.outstanding').value,
                  value: formatMoney(props.invoice.outstanding),
                  strong: true,
              },
          ]
        : [],
);
</script>

<template>
    <Head :title="invoice.number" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="invoice.number">
            <template #actions>
                <Button variant="ghost" size="sm" as-child>
                    <a
                        :href="pdf(invoice.id).url"
                        target="_blank"
                        rel="noopener"
                    >
                        <Eye class="size-4" />
                        {{ $t('document.preview') }}
                    </a>
                </Button>
                <Button variant="secondary" size="sm" as-child>
                    <a :href="pdf(invoice.id, { query: { download: 1 } }).url">
                        <Download class="size-4" />
                        {{ $t('document.download') }}
                    </a>
                </Button>

                <Button
                    v-if="invoice.is_editable"
                    variant="secondary"
                    size="sm"
                    as-child
                >
                    <Link :href="edit(invoice.id)">
                        <Pencil class="size-4" />
                        {{ $t('common.edit') }}
                    </Link>
                </Button>

                <Button
                    v-if="!isVoid && canEmail"
                    variant="secondary"
                    size="sm"
                    class="gap-1.5"
                    @click="sendEmail"
                >
                    <Mail class="size-4" />
                    {{ $t('invoice.send') }}
                </Button>

                <Button
                    v-if="!isVoid && invoice.status === 'draft'"
                    variant="ghost"
                    size="sm"
                    class="gap-1.5"
                    @click="markAsSent"
                >
                    <Send class="size-4" />
                    {{ $t('invoice.mark_sent') }}
                </Button>

                <Button
                    v-if="!isVoid && !isPaid"
                    size="sm"
                    class="gap-1.5"
                    @click="openPay"
                >
                    <Plus class="size-4" />
                    {{ $t('invoice.record_payment') }}
                </Button>

                <ConfirmDialog
                    v-if="!isVoid && invoice.status !== 'draft'"
                    :title="$t('invoice.void_confirm')"
                    :description="$t('invoice.void_description')"
                    @confirm="makeVoid"
                >
                    <template #trigger>
                        <Button variant="ghost" size="sm" class="text-warning">
                            <Ban class="size-4" />
                        </Button>
                    </template>
                </ConfirmDialog>

                <ConfirmDialog
                    v-if="invoice.status === 'draft'"
                    :title="$t('invoice.delete_confirm')"
                    :description="$t('invoice.delete_description')"
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

            <div v-if="invoice.project_name" class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('project.one') }}
                </p>
                <p class="text-sm">{{ invoice.project_name }}</p>
            </div>

            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('invoice.issue_date') }}
                </p>
                <p class="text-sm">{{ formatDate(invoice.issue_date) }}</p>
            </div>

            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('invoice.due_date') }}
                </p>
                <p
                    class="text-sm"
                    :class="invoice.is_late ? 'text-expense' : ''"
                >
                    {{ formatDate(invoice.due_date) }}
                </p>
            </div>

            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('document.language') }}
                </p>
                <p class="text-sm">{{ invoice.locale.toUpperCase() }}</p>
            </div>

            <div class="ml-auto space-y-1.5 text-right">
                <p class="text-xs text-muted-foreground">
                    {{ $t('common.status') }}
                </p>
                <StatusBadge
                    :label="invoice.status_label"
                    :tone="invoice.status_tone"
                />
            </div>
        </section>

        <p
            v-if="!canEmail"
            class="flex items-center gap-2 rounded-xl border border-border bg-surface-muted px-4 py-3 text-sm text-muted-foreground"
        >
            <TriangleAlert class="size-4 shrink-0" />
            {{ $t('invoice.error.no_client_email') }}
        </p>

        <!-- The link a client can open without an account -->
        <section
            v-if="publicLink !== null"
            class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-surface-muted px-4 py-3"
        >
            <Link2 class="size-4 shrink-0 text-muted-foreground" />
            <div class="min-w-0 flex-1 space-y-0.5">
                <p class="text-sm font-medium">
                    {{ $t('invoice.client_link') }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ publicLink }}
                </p>
            </div>
            <div class="flex items-center gap-1.5">
                <Button variant="ghost" size="sm" @click="copyLink">
                    <Copy class="size-3.5" />
                    {{ copied ? $t('invoice.link_copied') : $t('common.copy') }}
                </Button>
                <Button variant="secondary" size="sm" as-child>
                    <a :href="publicLink" target="_blank" rel="noopener">
                        <Eye class="size-3.5" />
                        {{ $t('document.preview') }}
                    </a>
                </Button>
            </div>
        </section>

        <p
            v-if="invoice.quotation_id !== null"
            class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-surface-muted px-4 py-3 text-sm"
        >
            <FileText class="size-4 shrink-0 text-muted-foreground" />
            <span>{{ $t('invoice.from_quotation') }}</span>
            <Link
                :href="showQuotation(invoice.quotation_id)"
                class="font-medium text-info underline-offset-4 hover:underline"
            >
                {{ invoice.quotation_number }}
            </Link>
        </p>

        <DocumentPreview
            :items="invoice.items ?? []"
            :subtotal="invoice.subtotal"
            :discount="invoice.discount"
            :total="invoice.total"
            :notes="invoice.notes"
            :terms="invoice.terms"
            :extra-totals="extraTotals"
        />

        <!-- Payments -->
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-sm font-medium">
                    {{ $t('invoice.payments') }}
                </h2>
                <Button
                    v-if="!isVoid && !isPaid"
                    variant="ghost"
                    size="sm"
                    @click="openPay"
                >
                    {{ $t('invoice.record_payment') }}
                </Button>
            </div>

            <p
                v-if="(invoice.payments ?? []).length === 0"
                class="rounded-xl border border-border bg-surface px-4 py-6 text-center text-sm text-muted-foreground"
            >
                {{ $t('invoice.no_payments') }}
            </p>

            <ul
                v-else
                class="divide-y divide-border rounded-xl border border-border bg-surface"
            >
                <li
                    v-for="payment in invoice.payments ?? []"
                    :key="payment.id"
                    class="flex items-center justify-between gap-4 px-4 py-3"
                >
                    <div class="min-w-0 space-y-0.5">
                        <p class="text-sm font-medium tabular-nums">
                            {{ formatMoney(payment.amount) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDate(payment.date) }}
                            <template v-if="payment.account_name">
                                · {{ payment.account_name }}
                            </template>
                        </p>
                    </div>

                    <ConfirmDialog
                        :title="$t('invoice.payment_remove_confirm')"
                        :description="$t('invoice.payment_remove_description')"
                        @confirm="dropPayment(payment)"
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
                </li>
            </ul>
        </section>
    </div>

    <Dialog v-model:open="payOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('invoice.record_payment') }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitPay">
                <div class="grid gap-2">
                    <Label for="pay-amount">{{ $t('common.amount') }}</Label>
                    <MoneyInput
                        id="pay-amount"
                        v-model="payForm.amount"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('invoice.outstanding') }}:
                        {{ formatMoney(invoice.outstanding) }}
                    </p>
                    <InputError :message="payForm.errors.amount" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="pay-date">{{ $t('common.date') }}</Label>
                        <Input
                            id="pay-date"
                            v-model="payForm.date"
                            type="date"
                            required
                        />
                        <InputError :message="payForm.errors.date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="pay-account">
                            {{ $t('transaction.account') }}
                        </Label>
                        <Select v-model="payForm.account_id">
                            <SelectTrigger id="pay-account">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="account in accounts"
                                    :key="account.id"
                                    :value="account.id"
                                >
                                    {{ account.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="payForm.errors.account_id" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="pay-category">
                        {{ $t('transaction.category') }}
                        <span class="text-muted-foreground">
                            ({{ $t('common.optional') }})
                        </span>
                    </Label>
                    <Select v-model="categoryValue">
                        <SelectTrigger id="pay-category">
                            <SelectValue :placeholder="$t('common.none')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">
                                {{ $t('common.none') }}
                            </SelectItem>
                            <SelectItem
                                v-for="category in incomeCategories"
                                :key="category.id"
                                :value="category.id.toString()"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="payForm.errors.category_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="pay-reference">
                        {{ $t('transaction.reference') }}
                    </Label>
                    <Input
                        id="pay-reference"
                        v-model="payForm.reference"
                        :placeholder="invoice.number"
                    />
                    <InputError :message="payForm.errors.reference" />
                </div>

                <p
                    class="rounded-xl border border-border bg-surface-muted p-3 text-xs text-muted-foreground"
                >
                    {{ $t('invoice.payment_allocation_hint') }}
                </p>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="payOpen = false"
                    >
                        {{ $t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="payForm.processing">
                        {{ $t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
