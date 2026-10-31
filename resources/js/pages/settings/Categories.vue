<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
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
import { destroy, index, store, update } from '@/routes/categories';
import type { Category, CategoryType, EnumOption, Fund } from '@/types';

const props = defineProps<{
    categories: Category[];
    funds: Fund[];
    categoryTypes: EnumOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'category.breadcrumb', href: index() }],
    },
});

const head = wTrans('category.title');
const description = wTrans('category.description');

const income = computed(() =>
    props.categories.filter((category) => category.type === 'income'),
);
const expense = computed(() =>
    props.categories.filter((category) => category.type === 'expense'),
);

const open = ref(false);
const editing = ref<Category | null>(null);

const form = useForm({
    name_ms: '',
    name_en: '',
    type: 'expense' as CategoryType,
    default_fund_id: null as number | null,
    tax_deductible_default: false,
    color: '#71717a',
});

const openCreate = (type: CategoryType) => {
    editing.value = null;
    form.defaults({
        name_ms: '',
        name_en: '',
        type,
        default_fund_id: null,
        tax_deductible_default: false,
        color: '#71717a',
    });
    form.reset();
    form.clearErrors();
    open.value = true;
};

const openEdit = (category: Category) => {
    editing.value = category;
    form.defaults({
        name_ms: category.name_ms,
        name_en: category.name_en,
        type: category.type,
        default_fund_id: category.default_fund_id,
        tax_deductible_default: category.tax_deductible_default,
        color: category.color,
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

const remove = (category: Category) => {
    router.delete(destroy(category.id).url, { preserveScroll: true });
};

const fundName = (id: number | null) =>
    props.funds.find((fund) => fund.id === id)?.name ?? null;
</script>

<template>
    <Head :title="head" />

    <h1 class="sr-only">{{ head }}</h1>

    <div class="space-y-10">
        <Heading variant="small" :title="head" :description="description" />

        <section
            v-for="group in [
                { type: 'income' as CategoryType, rows: income },
                { type: 'expense' as CategoryType, rows: expense },
            ]"
            :key="group.type"
            class="space-y-3"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-medium">
                    {{
                        categoryTypes.find((t) => t.value === group.type)?.label
                    }}
                </h2>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    @click="openCreate(group.type)"
                >
                    <Plus class="size-3.5" />
                    {{ $t('category.new') }}
                </Button>
            </div>

            <ul
                class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-surface"
            >
                <li
                    v-for="category in group.rows"
                    :key="category.id"
                    class="flex items-center gap-3 p-3"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: category.color }"
                    />
                    <div class="min-w-0 flex-1 space-y-0.5">
                        <p class="truncate text-sm font-medium">
                            {{ category.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            <template v-if="fundName(category.default_fund_id)">
                                {{ fundName(category.default_fund_id) }}
                            </template>
                            <template v-if="category.tax_deductible_default">
                                · {{ $t('transaction.tax_deductible') }}
                            </template>
                            <template v-if="category.transactions_count">
                                ·
                                {{
                                    $t('category.usage', {
                                        count: String(
                                            category.transactions_count,
                                        ),
                                    })
                                }}
                            </template>
                        </p>
                    </div>

                    <StatusBadge
                        v-if="category.is_system"
                        :label="$t('category.system')"
                    />

                    <Button
                        variant="ghost"
                        size="sm"
                        @click="openEdit(category)"
                    >
                        {{ $t('common.edit') }}
                    </Button>

                    <ConfirmDialog
                        v-if="!category.is_system"
                        :title="$t('category.delete_confirm')"
                        @confirm="remove(category)"
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

                <li
                    v-if="group.rows.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    {{ $t('category.empty_title') }}
                </li>
            </ul>
        </section>
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        editing === null
                            ? $t('category.new')
                            : $t('category.edit')
                    }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name_ms">{{ $t('category.name_ms') }}</Label>
                    <Input
                        id="name_ms"
                        v-model="form.name_ms"
                        required
                        v-focus
                    />
                    <InputError :message="form.errors.name_ms" />
                </div>

                <div class="grid gap-2">
                    <Label for="name_en">{{ $t('category.name_en') }}</Label>
                    <Input id="name_en" v-model="form.name_en" />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('category.name_en_hint') }}
                    </p>
                    <InputError :message="form.errors.name_en" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="type">{{ $t('common.type') }}</Label>
                        <Select
                            v-model="form.type"
                            :disabled="editing?.is_system === true"
                        >
                            <SelectTrigger id="type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in categoryTypes"
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
                        <Label for="color">{{ $t('common.color') }}</Label>
                        <Input
                            id="color"
                            v-model="form.color"
                            type="color"
                            class="h-9 p-1"
                        />
                        <InputError :message="form.errors.color" />
                    </div>
                </div>

                <div v-if="form.type === 'expense'" class="grid gap-2">
                    <Label for="default_fund_id">
                        {{ $t('category.default_fund') }}
                    </Label>
                    <Select v-model="form.default_fund_id">
                        <SelectTrigger id="default_fund_id">
                            <SelectValue :placeholder="$t('common.none')" />
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
                    <p class="text-xs text-muted-foreground">
                        {{ $t('category.default_fund_hint') }}
                    </p>
                    <InputError :message="form.errors.default_fund_id" />
                </div>

                <Label
                    for="tax_deductible_default"
                    class="flex items-center gap-3 font-normal"
                >
                    <Checkbox
                        id="tax_deductible_default"
                        v-model="form.tax_deductible_default"
                    />
                    <span>{{ $t('category.tax_deductible_default') }}</span>
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
