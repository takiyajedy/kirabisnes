<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { HandCoins, Info, PiggyBank, TriangleAlert } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import DataTable from '@/components/app/DataTable.vue';
import type { DataTableColumn } from '@/components/app/DataTable.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatCard from '@/components/app/StatCard.vue';
import TransactionFormDialog from '@/components/app/TransactionFormDialog.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatNumber } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/owner-pay';
import type {
    Fund,
    OwnerPaySuggestion,
    Paginated,
    Transaction,
    TransactionFormOptions,
    TransactionPrefill,
} from '@/types';

const props = defineProps<{
    fund: Fund;
    suggestion: OwnerPaySuggestion;
    drawnThisYear: number;
    year: number;
    history: Paginated<Transaction>;
    formOptions: TransactionFormOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'owner_pay.title', href: index() }],
    },
});

const title = wTrans('owner_pay.title');
const description = wTrans('owner_pay.description');

const balance = computed(() => props.fund.balance ?? 0);
const isNegative = computed(() => balance.value < 0);

/** Which of the two limits decided the suggestion, so the page can say so. */
const cappedByBalance = computed(
    () => props.suggestion.amount === balance.value && balance.value >= 0,
);

const formula = computed(
    () =>
        wTrans('owner_pay.formula', {
            months: String(props.suggestion.months),
            average: formatMoney(props.suggestion.average_income),
            percentage: formatNumber(props.suggestion.percentage),
        }).value,
);

// --- taking the money ----------------------------------------------------
const drawOpen = ref(false);

const prefill = computed<TransactionPrefill>(() => ({
    type: 'owner_draw',
    amount: props.suggestion.amount,
    fund_id: props.fund.id,
    description: wTrans('owner_pay.draw_description').value,
}));

const columns = computed<DataTableColumn[]>(() => [
    { key: 'date', label: wTrans('common.date').value, class: 'w-32' },
    { key: 'description', label: wTrans('common.description').value },
    {
        key: 'account',
        label: wTrans('transaction.account').value,
        class: 'w-44',
    },
    { key: 'amount', label: wTrans('common.amount').value, numeric: true },
]);
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="title" :description="description">
            <template #actions>
                <Button
                    class="gap-1.5"
                    :disabled="suggestion.amount <= 0"
                    @click="drawOpen = true"
                >
                    <HandCoins class="size-4" />
                    {{ $t('owner_pay.take') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-3">
            <StatCard
                :label="$t('owner_pay.fund_balance')"
                :amount="balance"
                :tone="isNegative ? 'expense' : 'income'"
                :icon="PiggyBank"
            />
            <StatCard
                :label="$t('owner_pay.suggested')"
                :amount="suggestion.amount"
                :icon="HandCoins"
                :hint="
                    cappedByBalance
                        ? $t('owner_pay.capped_by_balance')
                        : $t('owner_pay.capped_by_average')
                "
            />
            <StatCard
                :label="$t('owner_pay.drawn_this_year', { year: String(year) })"
                :amount="drawnThisYear"
                :icon="HandCoins"
            />
        </div>

        <!-- How the suggestion was worked out -->
        <section
            class="space-y-3 rounded-xl border border-border bg-surface-muted p-5"
        >
            <h2 class="flex items-center gap-1.5 text-sm font-medium">
                <Info class="size-4 text-info" />
                {{ $t('owner_pay.how_it_works') }}
            </h2>

            <p class="text-sm text-muted-foreground">
                {{ $t('owner_pay.explanation') }}
            </p>

            <dl class="grid gap-3 sm:grid-cols-3">
                <div class="space-y-0.5">
                    <dt class="text-xs text-muted-foreground">
                        {{ $t('owner_pay.fund_balance') }}
                    </dt>
                    <dd class="text-sm font-medium tabular-nums">
                        {{ formatMoney(balance) }}
                    </dd>
                </div>
                <div class="space-y-0.5">
                    <dt class="text-xs text-muted-foreground">
                        {{
                            $t('owner_pay.average_income', {
                                months: String(suggestion.months),
                            })
                        }}
                    </dt>
                    <dd class="text-sm font-medium tabular-nums">
                        {{ formatMoney(suggestion.average_income) }}
                    </dd>
                </div>
                <div class="space-y-0.5">
                    <dt class="text-xs text-muted-foreground">
                        {{ $t('owner_pay.share') }}
                    </dt>
                    <dd class="text-sm font-medium tabular-nums">
                        {{ formatNumber(suggestion.percentage) }}%
                    </dd>
                </div>
            </dl>

            <p class="text-xs text-muted-foreground">{{ formula }}</p>

            <p
                v-if="isNegative"
                class="flex items-center gap-1.5 text-xs text-expense"
            >
                <TriangleAlert class="size-3.5 shrink-0" />
                {{ $t('owner_pay.negative_warning') }}
            </p>
        </section>

        <!-- History -->
        <section class="space-y-3">
            <h2 class="text-sm font-medium">{{ $t('owner_pay.history') }}</h2>

            <DataTable
                :columns="columns"
                :rows="history.data"
                :meta="history.meta"
            >
                <template #[`cell:date`]="{ row }">
                    <span class="text-sm whitespace-nowrap">
                        {{ formatDate(row.date) }}
                    </span>
                </template>

                <template #[`cell:description`]="{ row }">
                    <span class="text-sm">{{ row.description }}</span>
                </template>

                <template #[`cell:account`]="{ row }">
                    <span class="truncate text-sm text-muted-foreground">
                        {{ row.account_name }}
                    </span>
                </template>

                <template #[`cell:amount`]="{ row }">
                    <span class="font-medium whitespace-nowrap text-expense">
                        −{{ formatMoney(row.amount) }}
                    </span>
                </template>
            </DataTable>
        </section>
    </div>

    <TransactionFormDialog
        v-model:open="drawOpen"
        :options="formOptions"
        :prefill="prefill"
    />
</template>
