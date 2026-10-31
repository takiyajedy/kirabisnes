<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileText, Paperclip } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import MoneyInput from '@/components/app/MoneyInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatNumber } from '@/lib/format';
import { asRinggit, formatMoney, toRinggit } from '@/lib/money';
import { allocationPreview, store, update } from '@/routes/transactions';
import type {
    AllocationShare,
    Transaction,
    TransactionFormOptions,
    TransactionPrefill,
    TransactionTypeValue,
} from '@/types';

const props = defineProps<{
    options: TransactionFormOptions;
    transaction?: Transaction | null;
    /**
     * Fields to start from when creating. The owner pay page uses this to
     * open the dialog on a draw for the suggested amount.
     */
    prefill?: TransactionPrefill | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const today = () => new Date().toISOString().slice(0, 10);

const form = useForm({
    type: 'income' as TransactionTypeValue,
    account_id: props.options.accounts[0]?.id ?? 0,
    to_account_id: null as number | null,
    amount: 0,
    date: today(),
    description: '',
    reference: '',
    category_id: null as number | null,
    fund_id: null as number | null,
    tax_deductible: false,
    receipt: null as File | null,
    remove_receipt: false,
});

// The form holds sen; the server is given ringgit.
form.transform((data) => asRinggit(data, 'amount'));

const isEditing = computed(() => props.transaction != null);

/** Load the dialog with either a blank form or the transaction being edited. */
const reset = () => {
    const t = props.transaction;
    // Only a new transaction starts from a prefill; an edit shows its own row.
    const p = t == null ? (props.prefill ?? null) : null;

    form.defaults({
        type: t?.type ?? p?.type ?? 'income',
        account_id: t?.account_id ?? props.options.accounts[0]?.id ?? 0,
        to_account_id: t?.to_account_id ?? null,
        amount: t?.amount ?? p?.amount ?? 0,
        date: t?.date ?? today(),
        description: t?.description ?? p?.description ?? '',
        reference: t?.reference ?? '',
        category_id: t?.category_id ?? null,
        fund_id: t?.fund_id ?? p?.fund_id ?? null,
        tax_deductible: t?.tax_deductible ?? false,
        receipt: null,
        remove_receipt: false,
    });
    form.reset();
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

// Which fields apply depends on the type (PLAN.md phase 1).
const isIncome = computed(() => form.type === 'income');
const isExpense = computed(() => form.type === 'expense');
const isTransfer = computed(() => form.type === 'transfer');
const hasCategory = computed(() => isIncome.value || isExpense.value);
const hasFund = computed(
    () =>
        isExpense.value ||
        form.type === 'owner_draw' ||
        form.type === 'capital_injection',
);
const changesFunds = computed(() => !isTransfer.value);

/** Only categories of the matching kind can be chosen. */
const categories = computed(() =>
    props.options.categories.filter((category) =>
        isIncome.value
            ? category.type === 'income'
            : category.type === 'expense',
    ),
);

// Picking a category pre-fills the tax checkbox and the fund, as the plan asks.
watch(
    () => form.category_id,
    (id) => {
        const category = props.options.categories.find((c) => c.id === id);

        if (category == null) {
            return;
        }

        form.tax_deductible = category.tax_deductible_default;

        if (isExpense.value && category.default_fund_id != null) {
            form.fund_id = category.default_fund_id;
        }
    },
);

// Clear fields that do not belong to the chosen type.
watch(
    () => form.type,
    () => {
        if (!hasCategory.value) {
            form.category_id = null;
        }

        if (!isTransfer.value) {
            form.to_account_id = null;
        }

        if (!isExpense.value) {
            form.tax_deductible = false;
            form.receipt = null;
        }

        if (!hasFund.value) {
            form.fund_id = null;
        }
    },
);

// --- income split preview -------------------------------------------------
// The maths lives in FundAllocationService, so the preview asks the server
// rather than keeping a second copy of it here.
const shares = ref<AllocationShare[]>([]);
let previewTimer: ReturnType<typeof setTimeout> | undefined;

const fetchPreview = async (amount: number) => {
    if (amount <= 0) {
        shares.value = [];

        return;
    }

    // Ringgit on the wire, as everywhere else; the form itself holds sen.
    const response = await fetch(
        allocationPreview.url({ query: { amount: toRinggit(amount) } }),
        { headers: { Accept: 'application/json' } },
    );

    if (!response.ok) {
        shares.value = [];

        return;
    }

    const data = (await response.json()) as { shares: AllocationShare[] };
    shares.value = data.shares;
};

watch(
    [() => form.amount, () => form.type, open],
    ([amount, type, isOpen]) => {
        clearTimeout(previewTimer);

        if (!isOpen || type !== 'income') {
            shares.value = [];

            return;
        }

        previewTimer = setTimeout(() => void fetchPreview(amount), 300);
    },
    { immediate: true },
);

const onReceiptChange = (event: Event) => {
    form.receipt = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.remove_receipt = false;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.transaction != null) {
        form.submit(update(props.transaction.id), options);

        return;
    }

    form.submit(store(), options);
};

const title = computed(() =>
    isEditing.value
        ? wTrans('transaction.edit').value
        : wTrans('transaction.new').value,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="type">{{ $t('common.type') }}</Label>
                    <Select v-model="form.type">
                        <SelectTrigger id="type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.types"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.type" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="amount">{{ $t('common.amount') }}</Label>
                        <MoneyInput
                            id="amount"
                            v-model="form.amount"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="date">{{ $t('common.date') }}</Label>
                        <Input
                            id="date"
                            v-model="form.date"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.date" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="description">
                        {{ $t('common.description') }}
                    </Label>
                    <Input
                        id="description"
                        v-model="form.description"
                        required
                        :placeholder="$t('transaction.description_placeholder')"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="account_id">
                            {{
                                isTransfer
                                    ? $t('transaction.from_account')
                                    : $t('transaction.account')
                            }}
                        </Label>
                        <Select v-model="form.account_id">
                            <SelectTrigger id="account_id">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="account in options.accounts"
                                    :key="account.id"
                                    :value="account.id"
                                >
                                    {{ account.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.account_id" />
                    </div>

                    <div v-if="isTransfer" class="grid gap-2">
                        <Label for="to_account_id">
                            {{ $t('transaction.to_account') }}
                        </Label>
                        <Select v-model="form.to_account_id">
                            <SelectTrigger id="to_account_id">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="account in options.accounts"
                                    :key="account.id"
                                    :value="account.id"
                                >
                                    {{ account.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.to_account_id" />
                    </div>
                </div>

                <div v-if="hasCategory" class="grid gap-2">
                    <Label for="category_id">
                        {{ $t('transaction.category') }}
                    </Label>
                    <Select v-model="form.category_id">
                        <SelectTrigger id="category_id">
                            <SelectValue :placeholder="$t('common.none')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.category_id" />
                </div>

                <div v-if="hasFund" class="grid gap-2">
                    <Label for="fund_id">{{ $t('transaction.fund') }}</Label>
                    <Select v-model="form.fund_id">
                        <SelectTrigger id="fund_id">
                            <SelectValue :placeholder="$t('common.none')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="fund in options.funds"
                                :key="fund.id"
                                :value="fund.id"
                            >
                                {{ fund.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        {{ $t('transaction.fund_hint') }}
                    </p>
                    <InputError :message="form.errors.fund_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="reference">
                        {{ $t('transaction.reference') }}
                    </Label>
                    <Input
                        id="reference"
                        v-model="form.reference"
                        :placeholder="$t('transaction.reference_placeholder')"
                    />
                    <InputError :message="form.errors.reference" />
                </div>

                <template v-if="isExpense">
                    <Label
                        for="tax_deductible"
                        class="flex items-center gap-3 font-normal"
                    >
                        <Checkbox
                            id="tax_deductible"
                            v-model="form.tax_deductible"
                        />
                        <span>{{ $t('transaction.tax_deductible') }}</span>
                    </Label>

                    <div class="grid gap-2">
                        <Label for="receipt">
                            {{ $t('transaction.receipt') }}
                        </Label>
                        <Input
                            id="receipt"
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            @change="onReceiptChange"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('transaction.receipt_hint') }}
                        </p>

                        <div
                            v-if="
                                transaction?.has_receipt && !form.remove_receipt
                            "
                            class="flex items-center gap-2 text-sm"
                        >
                            <a
                                :href="transaction.receipt_url ?? '#'"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 text-info underline-offset-4 hover:underline"
                            >
                                <Paperclip class="size-3.5" />
                                {{ $t('transaction.receipt_view') }}
                            </a>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="form.remove_receipt = true"
                            >
                                {{ $t('transaction.receipt_remove') }}
                            </Button>
                        </div>

                        <InputError :message="form.errors.receipt" />
                    </div>
                </template>

                <!-- How this income will be split across the funds -->
                <div
                    v-if="isIncome && shares.length > 0"
                    class="space-y-2 rounded-xl border border-border bg-surface-muted p-4"
                >
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ $t('transaction.allocation_preview') }}
                    </p>
                    <ul class="space-y-1">
                        <li
                            v-for="share in shares"
                            :key="share.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span
                                class="size-2 shrink-0 rounded-full"
                                :style="{ backgroundColor: share.color }"
                            />
                            <span class="flex-1 truncate">{{
                                share.name
                            }}</span>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ formatNumber(share.percentage) }}%
                            </span>
                            <span class="font-medium tabular-nums">
                                {{ formatMoney(share.amount) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <p
                    v-else-if="!changesFunds"
                    class="flex items-center gap-1.5 rounded-xl border border-border bg-surface-muted p-4 text-xs text-muted-foreground"
                >
                    <FileText class="size-3.5 shrink-0" />
                    {{ $t('transaction.allocation_none') }}
                </p>

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
