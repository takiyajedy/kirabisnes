<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    ArrowLeftRight,
    Pencil,
    Plus,
    Scale,
    TrendingDown,
    Wallet,
} from '@lucide/vue';
import DocumentList from '@/components/app/DocumentList.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import ProjectFormDialog from '@/components/app/ProjectFormDialog.vue';
import ProjectTimeSection from '@/components/app/ProjectTimeSection.vue';
import StatCard from '@/components/app/StatCard.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { show as showClient } from '@/routes/clients';
import { create as createInvoice } from '@/routes/invoices';
import { index } from '@/routes/projects';
import { create as createQuotation } from '@/routes/quotations';
import type {
    Client,
    EnumOption,
    Invoice,
    Project,
    ProjectProfit,
    Quotation,
    TimeEntry,
    Transaction,
} from '@/types';

const props = defineProps<{
    project: Project;
    client: Client;
    invoices: Invoice[];
    quotations: Quotation[];
    transactions: Transaction[];
    profit: ProjectProfit;
    timeEntries: TimeEntry[];
    statuses: EnumOption[];
    clients: Client[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'project.title', href: index() }],
    },
});

const documentQuery = {
    query: {
        client_id: props.project.client_id,
        project_id: props.project.id,
    },
};

const editOpen = ref(false);

const isInflow = (transaction: Transaction) =>
    transaction.type === 'income' || transaction.type === 'capital_injection';

const signedAmount = (transaction: Transaction) =>
    isInflow(transaction)
        ? formatMoney(transaction.amount)
        : `−${formatMoney(transaction.amount)}`;
</script>

<template>
    <Head :title="project.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="project.name">
            <template #actions>
                <StatusBadge
                    :label="project.status_label"
                    :tone="project.status_tone"
                />
                <Button
                    variant="secondary"
                    class="gap-1.5"
                    @click="editOpen = true"
                >
                    <Pencil class="size-4" />
                    {{ $t('common.edit') }}
                </Button>
                <Button variant="secondary" class="gap-1.5" as-child>
                    <Link :href="createQuotation(documentQuery)">
                        <Plus class="size-4" />
                        {{ $t('quotation.new') }}
                    </Link>
                </Button>
                <Button class="gap-1.5" as-child>
                    <Link :href="createInvoice(documentQuery)">
                        <Plus class="size-4" />
                        {{ $t('invoice.new') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Who it is for -->
        <section
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-surface p-5"
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

            <div v-if="project.start_date" class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('project.start_date') }}
                </p>
                <p class="text-sm">{{ formatDate(project.start_date) }}</p>
            </div>

            <div v-if="project.end_date" class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('project.end_date') }}
                </p>
                <p class="text-sm">{{ formatDate(project.end_date) }}</p>
            </div>

            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('project.contract_amount') }}
                </p>
                <p class="text-sm font-medium tabular-nums">
                    {{ formatMoney(project.contract_amount) }}
                </p>
            </div>
        </section>

        <p
            v-if="project.description"
            class="text-sm whitespace-pre-line text-muted-foreground"
        >
            {{ project.description }}
        </p>

        <!-- Gross profit -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :label="$t('project.received')"
                :amount="project.summary?.received ?? 0"
                tone="income"
                :icon="Wallet"
            />
            <StatCard
                :label="$t('project.costs')"
                :amount="project.summary?.costs ?? 0"
                tone="expense"
                :icon="TrendingDown"
            />
            <StatCard
                :label="$t('project.gross_profit')"
                :amount="project.summary?.gross_profit ?? 0"
                :tone="
                    (project.summary?.gross_profit ?? 0) < 0
                        ? 'expense'
                        : 'default'
                "
                :icon="Scale"
                :hint="$t('project.gross_profit_hint')"
            />
            <StatCard
                :label="$t('invoice.outstanding')"
                :amount="project.summary?.outstanding ?? 0"
                :tone="
                    (project.summary?.outstanding ?? 0) > 0
                        ? 'warning'
                        : 'default'
                "
            />
        </div>

        <!-- Documents -->
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-3">
                <h2 class="text-sm font-medium">{{ $t('invoice.title') }}</h2>
                <DocumentList
                    :documents="invoices"
                    kind="invoice"
                    :empty-title="$t('invoice.empty_title')"
                />
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-medium">{{ $t('quotation.title') }}</h2>
                <DocumentList
                    :documents="quotations"
                    kind="quotation"
                    :empty-title="$t('quotation.empty_title')"
                />
            </section>
        </div>

        <!-- Time logged, and what it cost -->
        <ProjectTimeSection
            :project="project"
            :profit="profit"
            :entries="timeEntries"
        />

        <!-- Everything tagged to this project -->
        <section class="space-y-3">
            <div class="space-y-1">
                <h2 class="text-sm font-medium">
                    {{ $t('project.tagged_transactions') }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ $t('project.tagged_transactions_hint') }}
                </p>
            </div>

            <EmptyState
                v-if="transactions.length === 0"
                :title="$t('transaction.empty_title')"
                :icon="ArrowLeftRight"
            />

            <ul
                v-else
                class="divide-y divide-border rounded-xl border border-border bg-surface"
            >
                <li
                    v-for="transaction in transactions"
                    :key="transaction.id"
                    class="flex items-center justify-between gap-4 px-4 py-3"
                >
                    <div class="min-w-0 space-y-0.5">
                        <p class="truncate text-sm font-medium">
                            {{ transaction.description }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDate(transaction.date) }} ·
                            {{ transaction.type_label }}
                            <template v-if="transaction.category_name">
                                · {{ transaction.category_name }}
                            </template>
                        </p>
                    </div>
                    <span
                        class="text-sm font-medium whitespace-nowrap"
                        :class="
                            isInflow(transaction)
                                ? 'text-income'
                                : 'text-expense'
                        "
                    >
                        {{ signedAmount(transaction) }}
                    </span>
                </li>
            </ul>
        </section>
    </div>

    <ProjectFormDialog
        v-model:open="editOpen"
        :project="project"
        :clients="clients"
        :statuses="statuses"
    />
</template>
