<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Landmark, Plus, Trash2, Wallet } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
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
import { asRinggit, formatMoney } from '@/lib/money';
import { destroy, index, store, update } from '@/routes/accounts';
import type { Account, EnumOption } from '@/types';

const props = defineProps<{
    accounts: Account[];
    totalBalance: number;
    accountTypes: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'account.title', href: index() }],
    },
});

const title = wTrans('account.title');
const description = wTrans('account.description');

const editing = ref<Account | null>(null);
const open = ref(false);

const form = useForm({
    name: '',
    type: 'bank' as Account['type'],
    bank_name: '',
    opening_balance: 0,
    is_active: true,
    sort_order: 0,
});

// The form holds sen; the server is given ringgit.
form.transform((data) => asRinggit(data, 'opening_balance'));

const openCreate = () => {
    editing.value = null;
    form.defaults({
        name: '',
        type: 'bank',
        bank_name: '',
        opening_balance: 0,
        is_active: true,
        sort_order: props.accounts.length,
    });
    form.reset();
    form.clearErrors();
    open.value = true;
};

const openEdit = (account: Account) => {
    editing.value = account;
    form.defaults({
        name: account.name,
        type: account.type,
        bank_name: account.bank_name ?? '',
        opening_balance: account.opening_balance,
        is_active: account.is_active,
        sort_order: account.sort_order,
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

const remove = (account: Account) => {
    router.delete(destroy(account.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="title" :description="description">
            <template #actions>
                <Button class="gap-1.5" @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('account.new') }}
                </Button>
            </template>
        </PageHeader>

        <StatCard
            :label="$t('account.total_balance')"
            :amount="totalBalance"
            :icon="Wallet"
            class="md:max-w-xs"
        />

        <EmptyState
            v-if="accounts.length === 0"
            :title="$t('account.empty_title')"
            :description="$t('account.empty_description')"
            :icon="Landmark"
        >
            <template #action>
                <Button class="gap-1.5" @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('account.new') }}
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="account in accounts"
                :key="account.id"
                class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-6"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="truncate font-medium">{{ account.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ account.type_label
                            }}<template v-if="account.bank_name">
                                · {{ account.bank_name }}</template
                            >
                        </p>
                    </div>
                    <StatusBadge
                        v-if="!account.is_active"
                        :label="$t('account.inactive')"
                    />
                </div>

                <div class="space-y-1">
                    <p class="text-xs text-muted-foreground">
                        {{ $t('account.current_balance') }}
                    </p>
                    <p
                        class="text-2xl font-semibold tracking-tight tabular-nums"
                        :class="
                            (account.balance ?? 0) < 0
                                ? 'text-expense'
                                : 'text-foreground'
                        "
                    >
                        {{ formatMoney(account.balance ?? 0) }}
                    </p>
                </div>

                <div class="mt-auto flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="openEdit(account)"
                    >
                        {{ $t('common.edit') }}
                    </Button>

                    <ConfirmDialog
                        :title="$t('account.delete_confirm')"
                        :description="$t('account.delete_description')"
                        @confirm="remove(account)"
                    >
                        <template #trigger>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 class="size-4" />
                                <span class="sr-only">
                                    {{ $t('common.delete') }}
                                </span>
                            </Button>
                        </template>
                    </ConfirmDialog>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        editing === null
                            ? $t('account.new')
                            : $t('account.edit')
                    }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('account.name') }}</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        required
                        v-focus
                        :placeholder="$t('account.name_placeholder')"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="type">{{ $t('common.type') }}</Label>
                        <Select v-model="form.type">
                            <SelectTrigger id="type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in accountTypes"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bank_name">
                            {{ $t('account.bank_name') }}
                        </Label>
                        <Input
                            id="bank_name"
                            v-model="form.bank_name"
                            :disabled="form.type === 'cash'"
                        />
                        <InputError :message="form.errors.bank_name" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="opening_balance">
                        {{ $t('account.opening_balance') }}
                    </Label>
                    <MoneyInput
                        id="opening_balance"
                        v-model="form.opening_balance"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('account.opening_balance_hint') }}
                    </p>
                    <InputError :message="form.errors.opening_balance" />
                </div>

                <Label
                    for="is_active"
                    class="flex items-center gap-3 font-normal"
                >
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <span>{{ $t('account.is_active') }}</span>
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
</template>
