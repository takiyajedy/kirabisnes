<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    BellRing,
    Check,
    Pencil,
    Plus,
    Repeat,
    SkipForward,
    Trash2,
} from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import MoneyInput from '@/components/app/MoneyInput.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { asRinggit, formatMoney } from '@/lib/money';
import { destroy, index, pay, skip, store, update } from '@/routes/recurring';
import type {
    Account,
    Category,
    Client,
    EnumOption,
    Project,
    RecurringItem,
    RecurringKindValue,
} from '@/types';

const props = defineProps<{
    items: RecurringItem[];
    kind: RecurringKindValue;
    kinds: EnumOption[];
    counts: Record<string, number>;
    intervals: EnumOption[];
    clients: Client[];
    projects: Project[];
    expenseCategories: Category[];
    accounts: Account[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'recurring.title', href: index() }],
    },
});

const title = wTrans('recurring.title');
const description = wTrans('recurring.description');

const NONE = '__none__';

const isClientBilling = computed(() => props.kind === 'client_billing');

const switchTab = (kind: string) => {
    router.get(index().url, { kind }, { preserveScroll: true, replace: true });
};

// --- create and edit -----------------------------------------------------
const open = ref(false);
const editing = ref<RecurringItem | null>(null);

const today = () => new Date().toISOString().slice(0, 10);

const form = useForm({
    kind: props.kind as RecurringKindValue,
    name: '',
    description: '',
    amount: 0,
    interval: 'monthly',
    next_date: today(),
    client_id: null as number | null,
    project_id: null as number | null,
    category_id: null as number | null,
    account_id: null as number | null,
    auto_generate: false,
    remind_days_before: 14,
    is_active: true,
});

// The form holds sen; the server is given ringgit.
form.transform((data) => asRinggit(data, 'amount'));

const load = (item: RecurringItem | null) => {
    editing.value = item;

    form.defaults({
        kind: item?.kind ?? props.kind,
        name: item?.name ?? '',
        description: item?.description ?? '',
        amount: item?.amount ?? 0,
        interval: item?.interval ?? 'monthly',
        next_date: item?.next_date ?? today(),
        client_id: item?.client_id ?? props.clients[0]?.id ?? null,
        project_id: item?.project_id ?? null,
        category_id: item?.category_id ?? null,
        account_id: item?.account_id ?? props.accounts[0]?.id ?? null,
        auto_generate: item?.auto_generate ?? false,
        remind_days_before: item?.remind_days_before ?? 14,
        is_active: item?.is_active ?? true,
    });
    form.reset();
    form.clearErrors();
    open.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (editing.value === null) {
        form.submit(store(), options);

        return;
    }

    form.submit(update(editing.value.id), options);
};

// Only this client's projects can be attached.
const availableProjects = computed(() =>
    props.projects.filter((project) => project.client_id === form.client_id),
);

const clientValue = computed({
    get: () => (form.client_id === null ? NONE : form.client_id.toString()),
    set: (value: string) => {
        form.client_id = value === NONE ? null : Number(value);
        form.project_id = null;
    },
});

const projectValue = computed({
    get: () => (form.project_id === null ? NONE : form.project_id.toString()),
    set: (value: string) => {
        form.project_id = value === NONE ? null : Number(value);
    },
});

const categoryValue = computed({
    get: () => (form.category_id === null ? NONE : form.category_id.toString()),
    set: (value: string) => {
        form.category_id = value === NONE ? null : Number(value);
    },
});

const accountValue = computed({
    get: () => (form.account_id === null ? NONE : form.account_id.toString()),
    set: (value: string) => {
        form.account_id = value === NONE ? null : Number(value);
    },
});

// A cost of your own cannot draft an invoice for anybody.
watch(
    () => form.kind,
    (kind) => {
        if (kind === 'own_cost') {
            form.auto_generate = false;
            form.client_id = null;
            form.project_id = null;
        }
    },
);

// --- paying and skipping -------------------------------------------------
const payOpen = ref(false);
const paying = ref<RecurringItem | null>(null);

const payForm = useForm({
    account_id: props.accounts[0]?.id ?? 0,
    date: today(),
});

const openPay = (item: RecurringItem) => {
    paying.value = item;
    payForm.defaults({
        account_id: item.account_id ?? props.accounts[0]?.id ?? 0,
        date: item.next_date,
    });
    payForm.reset();
    payForm.clearErrors();
    payOpen.value = true;
};

const submitPay = () => {
    if (paying.value === null) {
        return;
    }

    payForm.submit(pay(paying.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            payOpen.value = false;
        },
    });
};

const skipItem = (item: RecurringItem) => {
    router.post(skip(item.id).url, {}, { preserveScroll: true });
};

const remove = (item: RecurringItem) => {
    router.delete(destroy(item.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="title" :description="description">
            <template #actions>
                <Button class="gap-1.5" @click="load(null)">
                    <Plus class="size-4" />
                    {{ $t('recurring.new') }}
                </Button>
            </template>
        </PageHeader>

        <!-- The two sides: what clients pay you, and what you pay for -->
        <div
            class="flex gap-1 rounded-xl border border-border bg-surface p-1"
            role="tablist"
        >
            <button
                v-for="option in kinds"
                :key="option.value"
                type="button"
                role="tab"
                :aria-selected="kind === option.value"
                class="flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                :class="
                    kind === option.value
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                "
                @click="switchTab(option.value)"
            >
                {{ option.label }}
                <span class="text-xs tabular-nums opacity-70">
                    {{ counts[option.value] ?? 0 }}
                </span>
            </button>
        </div>

        <EmptyState
            v-if="items.length === 0"
            :title="$t('recurring.empty_title')"
            :description="
                isClientBilling
                    ? $t('recurring.empty_client_billing')
                    : $t('recurring.empty_own_cost')
            "
            :icon="Repeat"
        >
            <template #action>
                <Button class="gap-1.5" @click="load(null)">
                    <Plus class="size-4" />
                    {{ $t('recurring.new') }}
                </Button>
            </template>
        </EmptyState>

        <ul v-else class="space-y-3">
            <li
                v-for="item in items"
                :key="item.id"
                class="rounded-xl border border-border bg-surface p-4"
                :class="item.is_due ? 'border-warning/40' : ''"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium">{{ item.name }}</p>
                            <StatusBadge
                                v-if="!item.is_active"
                                :label="$t('recurring.inactive')"
                                tone="neutral"
                            />
                            <StatusBadge
                                v-else-if="item.is_due"
                                :label="$t('recurring.due_now')"
                                tone="warning"
                            />
                            <StatusBadge
                                v-if="item.auto_generate"
                                :label="$t('recurring.auto_badge')"
                                tone="info"
                            />
                        </div>

                        <p class="text-xs text-muted-foreground">
                            {{ item.interval_label }} ·
                            {{ $t('recurring.next_charge') }}:
                            {{ formatDate(item.next_date) }}
                            <template v-if="item.client_name">
                                · {{ item.client_name }}
                            </template>
                            <template v-if="item.category_name">
                                · {{ item.category_name }}
                            </template>
                        </p>

                        <p
                            v-if="item.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ item.description }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <p
                            class="text-lg font-semibold tracking-tight tabular-nums"
                        >
                            {{ formatMoney(item.amount) }}
                        </p>

                        <div class="flex items-center gap-1">
                            <Button
                                v-if="item.kind === 'own_cost'"
                                variant="secondary"
                                size="sm"
                                class="gap-1.5"
                                @click="openPay(item)"
                            >
                                <Check class="size-3.5" />
                                {{ $t('recurring.mark_paid') }}
                            </Button>

                            <Button
                                v-else
                                variant="ghost"
                                size="sm"
                                class="gap-1.5"
                                :title="$t('recurring.skip_hint')"
                                @click="skipItem(item)"
                            >
                                <SkipForward class="size-3.5" />
                                {{ $t('recurring.skip') }}
                            </Button>

                            <Button
                                variant="ghost"
                                size="sm"
                                @click="load(item)"
                            >
                                <Pencil class="size-3.5" />
                                <span class="sr-only">{{
                                    $t('common.edit')
                                }}</span>
                            </Button>

                            <ConfirmDialog
                                :title="$t('recurring.delete_confirm')"
                                :description="
                                    $t('recurring.delete_description')
                                "
                                @confirm="remove(item)"
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
                        </div>
                    </div>
                </div>

                <p
                    v-if="item.is_due && item.auto_generate"
                    class="mt-3 flex items-center gap-1.5 text-xs text-info"
                >
                    <BellRing class="size-3.5 shrink-0" />
                    {{ $t('recurring.will_draft') }}
                </p>
            </li>
        </ul>

        <p class="text-xs text-muted-foreground">
            {{ $t('recurring.schedule_hint') }}
        </p>
    </div>

    <!-- Create / edit -->
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? $t('recurring.edit') : $t('recurring.new') }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="kind">{{ $t('recurring.kind_label') }}</Label>
                    <Select v-model="form.kind">
                        <SelectTrigger id="kind">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in kinds"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.kind" />
                </div>

                <div class="grid gap-2">
                    <Label for="recurring-name">{{
                        $t('recurring.name')
                    }}</Label>
                    <Input
                        id="recurring-name"
                        v-model="form.name"
                        required
                        :placeholder="$t('recurring.name_placeholder')"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="recurring-amount">
                            {{ $t('common.amount') }}
                        </Label>
                        <MoneyInput
                            id="recurring-amount"
                            v-model="form.amount"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="interval">{{
                            $t('recurring.interval_label')
                        }}</Label>
                        <Select v-model="form.interval">
                            <SelectTrigger id="interval">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in intervals"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.interval" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="next_date">{{
                            $t('recurring.next_charge')
                        }}</Label>
                        <Input
                            id="next_date"
                            v-model="form.next_date"
                            type="date"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('recurring.anchor_hint') }}
                        </p>
                        <InputError :message="form.errors.next_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="remind_days_before">
                            {{ $t('recurring.remind_days_before') }}
                        </Label>
                        <Input
                            id="remind_days_before"
                            v-model="form.remind_days_before"
                            type="number"
                            min="0"
                            max="180"
                            class="tabular-nums"
                        />
                        <InputError :message="form.errors.remind_days_before" />
                    </div>
                </div>

                <!-- Who it is for -->
                <template v-if="form.kind === 'client_billing'">
                    <div class="grid gap-2">
                        <Label for="recurring-client">{{
                            $t('client.one')
                        }}</Label>
                        <Select v-model="clientValue">
                            <SelectTrigger id="recurring-client">
                                <SelectValue :placeholder="$t('common.none')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="client in clients"
                                    :key="client.id"
                                    :value="client.id.toString()"
                                >
                                    {{ client.display_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.client_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="recurring-project">
                            {{ $t('project.one') }}
                            <span class="text-muted-foreground">
                                ({{ $t('common.optional') }})
                            </span>
                        </Label>
                        <Select v-model="projectValue">
                            <SelectTrigger id="recurring-project">
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

                    <Label
                        for="auto_generate"
                        class="flex items-start gap-3 font-normal"
                    >
                        <Checkbox
                            id="auto_generate"
                            v-model="form.auto_generate"
                            class="mt-0.5"
                        />
                        <span class="space-y-0.5">
                            <span class="block text-sm">
                                {{ $t('recurring.auto_generate') }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{ $t('recurring.auto_generate_hint') }}
                            </span>
                        </span>
                    </Label>
                </template>

                <!-- What it is filed under -->
                <template v-else>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="recurring-category">
                                {{ $t('transaction.category') }}
                            </Label>
                            <Select v-model="categoryValue">
                                <SelectTrigger id="recurring-category">
                                    <SelectValue
                                        :placeholder="$t('common.none')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">
                                        {{ $t('common.none') }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="category in expenseCategories"
                                        :key="category.id"
                                        :value="category.id.toString()"
                                    >
                                        {{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="recurring-account">
                                {{ $t('transaction.account') }}
                            </Label>
                            <Select v-model="accountValue">
                                <SelectTrigger id="recurring-account">
                                    <SelectValue
                                        :placeholder="$t('common.none')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">
                                        {{ $t('common.none') }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="account in accounts"
                                        :key="account.id"
                                        :value="account.id.toString()"
                                    >
                                        {{ account.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.account_id" />
                        </div>
                    </div>
                </template>

                <div class="grid gap-2">
                    <Label for="recurring-description">
                        {{ $t('common.description') }}
                    </Label>
                    <Textarea
                        id="recurring-description"
                        v-model="form.description"
                        rows="2"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <Label
                    for="is_active"
                    class="flex items-center gap-3 font-normal"
                >
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <span>{{ $t('recurring.active') }}</span>
                </Label>

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

    <!-- Mark an own cost paid -->
    <Dialog v-model:open="payOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('recurring.mark_paid') }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitPay">
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('recurring.mark_paid_description', {
                            name: paying?.name ?? '',
                            amount: formatMoney(paying?.amount ?? 0),
                        })
                    }}
                </p>

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

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="payOpen = false"
                    >
                        {{ $t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="payForm.processing">
                        {{ $t('recurring.mark_paid') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
