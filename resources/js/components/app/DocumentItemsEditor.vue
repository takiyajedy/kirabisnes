<script setup lang="ts">
import { GripVertical, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import MoneyInput from '@/components/app/MoneyInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/money';
import type { DocumentItemInput } from '@/types';

/**
 * The line items of a quotation or an invoice.
 *
 * The figures shown beside each line are a preview only: the amounts that get
 * stored are worked out again on the server by DocumentTotals, so there is
 * one place where money is multiplied (PLAN.md 3.2).
 */
const items = defineModel<DocumentItemInput[]>('items', { required: true });
const discount = defineModel<number>('discount', { required: true });

const props = defineProps<{
    errors: Record<string, string>;
}>();

const blank = (): DocumentItemInput => ({
    description: '',
    quantity: 1,
    unit_price: 0,
});

const add = () => {
    items.value = [...items.value, blank()];
};

const remove = (index: number) => {
    // A document with no lines has nothing to charge for.
    items.value =
        items.value.length === 1
            ? [blank()]
            : items.value.filter((_, i) => i !== index);
};

const move = (index: number, by: number) => {
    const target = index + by;

    if (target < 0 || target >= items.value.length) {
        return;
    }

    const next = [...items.value];
    [next[index], next[target]] = [next[target], next[index]];
    items.value = next;
};

const lineAmount = (item: DocumentItemInput) =>
    Math.round(Number(item.quantity || 0) * 100 * (item.unit_price || 0)) / 100;

const subtotal = computed(() =>
    items.value.reduce((sum, item) => sum + lineAmount(item), 0),
);

const total = computed(() =>
    Math.max(0, subtotal.value - Math.max(0, discount.value || 0)),
);

const errorFor = (index: number, field: string) =>
    props.errors[`items.${index}.${field}`] ?? '';
</script>

<template>
    <div class="space-y-3">
        <!-- Desktop: a table. Mobile: a card per line (PLAN.md 4.3). -->
        <div
            class="hidden overflow-hidden rounded-xl border border-border md:block"
        >
            <table class="w-full text-sm">
                <thead class="bg-surface-muted">
                    <tr>
                        <th class="w-8"></th>
                        <th
                            class="px-3 py-2.5 text-left text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('document.item.description') }}
                        </th>
                        <th
                            class="w-24 px-3 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('document.item.quantity') }}
                        </th>
                        <th
                            class="w-40 px-3 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('document.item.unit_price') }}
                        </th>
                        <th
                            class="w-32 px-3 py-2.5 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('document.item.amount') }}
                        </th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in items"
                        :key="index"
                        class="border-t border-border"
                    >
                        <td class="px-1 py-2 align-top">
                            <div class="flex flex-col items-center">
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="index === 0"
                                    :aria-label="$t('document.item.move_up')"
                                    @click="move(index, -1)"
                                >
                                    <GripVertical class="size-3.5" />
                                </button>
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <Input
                                v-model="item.description"
                                :placeholder="
                                    $t('document.item.description_placeholder')
                                "
                                required
                            />
                            <InputError
                                :message="errorFor(index, 'description')"
                            />
                        </td>
                        <td class="px-3 py-2 align-top">
                            <Input
                                v-model="item.quantity"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="text-right tabular-nums"
                                required
                            />
                            <InputError
                                :message="errorFor(index, 'quantity')"
                            />
                        </td>
                        <td class="px-3 py-2 align-top">
                            <MoneyInput v-model="item.unit_price" required />
                            <InputError
                                :message="errorFor(index, 'unit_price')"
                            />
                        </td>
                        <td
                            class="px-3 py-4 text-right align-top font-medium tabular-nums"
                        >
                            {{ formatMoney(lineAmount(item)) }}
                        </td>
                        <td class="px-1 py-2 text-right align-top">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                :aria-label="$t('document.item.remove')"
                                @click="remove(index)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-3 md:hidden">
            <div
                v-for="(item, index) in items"
                :key="index"
                class="space-y-3 rounded-xl border border-border bg-surface p-4"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        {{ $t('document.item.one') }} {{ index + 1 }}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive"
                        @click="remove(index)"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>

                <div class="grid gap-1.5">
                    <Label>{{ $t('document.item.description') }}</Label>
                    <Input v-model="item.description" required />
                    <InputError :message="errorFor(index, 'description')" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>{{ $t('document.item.quantity') }}</Label>
                        <Input
                            v-model="item.quantity"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="text-right tabular-nums"
                            required
                        />
                        <InputError :message="errorFor(index, 'quantity')" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>{{ $t('document.item.unit_price') }}</Label>
                        <MoneyInput v-model="item.unit_price" required />
                        <InputError :message="errorFor(index, 'unit_price')" />
                    </div>
                </div>

                <p class="text-right text-sm font-medium tabular-nums">
                    {{ formatMoney(lineAmount(item)) }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <Button
                type="button"
                variant="secondary"
                class="gap-1.5"
                @click="add"
            >
                <Plus class="size-4" />
                {{ $t('document.item.add') }}
            </Button>

            <div class="w-full space-y-2 sm:w-64">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-muted-foreground">
                        {{ $t('document.subtotal') }}
                    </span>
                    <span class="font-medium tabular-nums">
                        {{ formatMoney(subtotal) }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <Label for="discount" class="text-muted-foreground">
                        {{ $t('document.discount') }}
                    </Label>
                    <MoneyInput id="discount" v-model="discount" class="w-32" />
                </div>
                <InputError :message="errors.discount ?? ''" />

                <div
                    class="flex items-center justify-between gap-4 border-t border-border pt-2"
                >
                    <span class="font-medium">{{ $t('document.total') }}</span>
                    <span
                        class="text-lg font-semibold tracking-tight tabular-nums"
                    >
                        {{ formatMoney(total) }}
                    </span>
                </div>
            </div>
        </div>

        <InputError :message="errors.items ?? ''" />
    </div>
</template>
