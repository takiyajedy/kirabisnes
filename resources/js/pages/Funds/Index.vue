<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, PiggyBank, TriangleAlert } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import MoneyInput from '@/components/app/MoneyInput.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatCard from '@/components/app/StatCard.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { asRinggit, formatMoney } from '@/lib/money';
import { index, transfer, update } from '@/routes/funds';
import type { Fund } from '@/types';

const props = defineProps<{
    funds: Fund[];
    totalBalance: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'fund.title', href: index() }],
    },
});

const title = wTrans('fund.title');
const description = wTrans('fund.description');

const sharesOpen = ref(false);
const transferOpen = ref(false);

const sharesForm = useForm({
    funds: props.funds.map((fund) => ({
        id: fund.id,
        percentage: fund.percentage,
        is_active: fund.is_active,
    })),
});

/** Live total, so the 100% rule is visible before submitting. */
const shareTotal = computed(() =>
    sharesForm.funds
        .filter((row) => row.is_active)
        .reduce((total, row) => total + Number(row.percentage || 0), 0),
);

const shareTotalIsValid = computed(
    () => Math.round(shareTotal.value * 100) === 10_000,
);

const fundName = (id: number) =>
    props.funds.find((fund) => fund.id === id)?.name ?? '';

const submitShares = () => {
    sharesForm.submit(update(), {
        preserveScroll: true,
        onSuccess: () => {
            sharesOpen.value = false;
        },
    });
};

const transferForm = useForm({
    from_fund_id: props.funds[0]?.id ?? 0,
    to_fund_id: props.funds[1]?.id ?? 0,
    amount: 0,
    date: new Date().toISOString().slice(0, 10),
    note: '',
});

// The form holds sen; the server is given ringgit.
transferForm.transform((data) => asRinggit(data, 'amount'));

const submitTransfer = () => {
    transferForm.submit(transfer(), {
        preserveScroll: true,
        onSuccess: () => {
            transferOpen.value = false;
            transferForm.reset('amount', 'note');
        },
    });
};

/** Width of the share bar, as a percentage string. */
const barWidth = (fund: Fund) => `${Math.min(fund.percentage, 100)}%`;
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="title" :description="description">
            <template #actions>
                <Button
                    variant="outline"
                    class="gap-1.5"
                    @click="transferOpen = true"
                >
                    <ArrowLeftRight class="size-4" />
                    {{ $t('fund.transfer') }}
                </Button>
                <Button @click="sharesOpen = true">
                    {{ $t('fund.edit_percentages') }}
                </Button>
            </template>
        </PageHeader>

        <StatCard
            :label="$t('fund.total_balance')"
            :amount="totalBalance"
            :icon="PiggyBank"
            class="md:max-w-xs"
        />

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="fund in funds"
                :key="fund.id"
                class="space-y-4 rounded-xl border border-border bg-surface p-6"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: fund.color }"
                        />
                        <p class="truncate font-medium">{{ fund.name }}</p>
                    </div>
                    <StatusBadge
                        v-if="!fund.is_active"
                        :label="$t('fund.inactive')"
                    />
                </div>

                <div class="space-y-1">
                    <p class="text-xs text-muted-foreground">
                        {{ $t('fund.balance') }}
                    </p>
                    <p
                        class="text-2xl font-semibold tracking-tight tabular-nums"
                        :class="
                            (fund.balance ?? 0) < 0
                                ? 'text-expense'
                                : 'text-foreground'
                        "
                    >
                        {{ formatMoney(fund.balance ?? 0) }}
                    </p>
                </div>

                <p
                    v-if="(fund.balance ?? 0) < 0"
                    class="flex items-center gap-1.5 text-xs text-expense"
                >
                    <TriangleAlert class="size-3.5 shrink-0" />
                    {{ $t('fund.negative_warning') }}
                </p>

                <div class="space-y-1.5">
                    <div
                        class="flex items-center justify-between text-xs text-muted-foreground"
                    >
                        <span>{{ $t('fund.percentage') }}</span>
                        <span class="tabular-nums">
                            {{ formatNumber(fund.percentage) }}%
                        </span>
                    </div>
                    <div
                        class="h-1.5 w-full overflow-hidden rounded-full bg-surface-muted"
                    >
                        <div
                            class="h-full rounded-full transition-all"
                            :style="{
                                width: barWidth(fund),
                                backgroundColor: fund.color,
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Percentage split -->
    <Dialog v-model:open="sharesOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('fund.edit_percentages') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('fund.percentages_description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitShares">
                <div
                    v-for="(row, i) in sharesForm.funds"
                    :key="row.id"
                    class="flex items-center gap-3"
                >
                    <Checkbox
                        :id="`active-${row.id}`"
                        v-model="sharesForm.funds[i].is_active"
                    />
                    <Label
                        :for="`share-${row.id}`"
                        class="flex-1 font-normal"
                        :class="{ 'opacity-50': !row.is_active }"
                    >
                        {{ fundName(row.id) }}
                    </Label>
                    <div class="relative w-28">
                        <Input
                            :id="`share-${row.id}`"
                            v-model="sharesForm.funds[i].percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="pr-7 text-right tabular-nums"
                            :disabled="!row.is_active"
                        />
                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                        >
                            %
                        </span>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                    :class="
                        shareTotalIsValid
                            ? 'border-border text-muted-foreground'
                            : 'border-expense/30 bg-expense-muted text-expense'
                    "
                >
                    <span>{{ $t('fund.total_share') }}</span>
                    <span class="font-medium tabular-nums">
                        {{ formatNumber(shareTotal) }}%
                    </span>
                </div>

                <InputError :message="sharesForm.errors.funds" />

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="sharesOpen = false"
                    >
                        {{ $t('common.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="sharesForm.processing || !shareTotalIsValid"
                    >
                        {{ $t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Move between funds -->
    <Dialog v-model:open="transferOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('fund.transfer') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('fund.transfer_description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitTransfer">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="from_fund_id">
                            {{ $t('fund.transfer_from') }}
                        </Label>
                        <Select v-model="transferForm.from_fund_id">
                            <SelectTrigger id="from_fund_id">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="fund in funds"
                                    :key="fund.id"
                                    :value="fund.id"
                                >
                                    {{ fund.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="transferForm.errors.from_fund_id"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="to_fund_id">
                            {{ $t('fund.transfer_to') }}
                        </Label>
                        <Select v-model="transferForm.to_fund_id">
                            <SelectTrigger id="to_fund_id">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="fund in funds"
                                    :key="fund.id"
                                    :value="fund.id"
                                >
                                    {{ fund.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="transferForm.errors.to_fund_id" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="amount">{{ $t('common.amount') }}</Label>
                        <MoneyInput id="amount" v-model="transferForm.amount" />
                        <InputError :message="transferForm.errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="date">{{ $t('common.date') }}</Label>
                        <Input
                            id="date"
                            v-model="transferForm.date"
                            type="date"
                            required
                        />
                        <InputError :message="transferForm.errors.date" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="note">{{ $t('common.notes') }}</Label>
                    <Input id="note" v-model="transferForm.note" />
                    <InputError :message="transferForm.errors.note" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="transferOpen = false"
                    >
                        {{ $t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="transferForm.processing">
                        {{ $t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
