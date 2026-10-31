<script setup lang="ts">
import { formatMoney } from '@/lib/money';
import type { DocumentItem } from '@/types';

/**
 * How a document reads on screen: the same lines and totals the PDF shows,
 * so what the owner approves is what the client receives.
 */
defineProps<{
    items: DocumentItem[];
    subtotal: number;
    discount: number;
    total: number;
    notes: string | null;
    terms: string | null;
    /** Extra rows under the total, such as what is paid and still owed. */
    extraTotals?: { label: string; value: string; strong?: boolean }[];
}>();
</script>

<template>
    <div class="space-y-5 rounded-xl border border-border bg-surface p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-foreground/80">
                        <th
                            class="w-8 pb-2 text-left text-xs font-medium text-muted-foreground"
                        >
                            #
                        </th>
                        <th
                            class="pb-2 text-left text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('document.item.description') }}
                        </th>
                        <th
                            class="w-20 pb-2 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('document.item.quantity') }}
                        </th>
                        <th
                            class="w-32 pb-2 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('document.item.unit_price') }}
                        </th>
                        <th
                            class="w-32 pb-2 text-right text-xs font-medium text-muted-foreground"
                        >
                            {{ $t('document.item.amount') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in items"
                        :key="item.id"
                        class="border-b border-border"
                    >
                        <td class="py-2.5 text-muted-foreground">
                            {{ index + 1 }}
                        </td>
                        <td class="py-2.5">{{ item.description }}</td>
                        <td class="py-2.5 text-right tabular-nums">
                            {{ item.quantity }}
                        </td>
                        <td class="py-2.5 text-right tabular-nums">
                            {{ formatMoney(item.unit_price) }}
                        </td>
                        <td class="py-2.5 text-right font-medium tabular-nums">
                            {{ formatMoney(item.amount) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <dl class="w-full space-y-1.5 sm:w-72">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <dt class="text-muted-foreground">
                        {{ $t('document.subtotal') }}
                    </dt>
                    <dd class="tabular-nums">{{ formatMoney(subtotal) }}</dd>
                </div>

                <div
                    v-if="discount > 0"
                    class="flex items-center justify-between gap-4 text-sm"
                >
                    <dt class="text-muted-foreground">
                        {{ $t('document.discount') }}
                    </dt>
                    <dd class="tabular-nums">−{{ formatMoney(discount) }}</dd>
                </div>

                <div
                    class="flex items-center justify-between gap-4 border-t border-foreground/80 pt-2"
                >
                    <dt class="font-medium">{{ $t('document.total') }}</dt>
                    <dd
                        class="text-lg font-semibold tracking-tight tabular-nums"
                    >
                        {{ formatMoney(total) }}
                    </dd>
                </div>

                <div
                    v-for="row in extraTotals ?? []"
                    :key="row.label"
                    class="flex items-center justify-between gap-4 text-sm"
                    :class="
                        row.strong ? 'font-medium' : 'text-muted-foreground'
                    "
                >
                    <dt>{{ row.label }}</dt>
                    <dd class="tabular-nums">{{ row.value }}</dd>
                </div>
            </dl>
        </div>

        <div
            v-if="notes || terms"
            class="grid gap-4 border-t border-border pt-4 sm:grid-cols-2"
        >
            <div v-if="notes" class="space-y-1">
                <p class="text-xs font-medium text-muted-foreground">
                    {{ $t('document.notes') }}
                </p>
                <p class="text-sm whitespace-pre-line">{{ notes }}</p>
            </div>
            <div v-if="terms" class="space-y-1">
                <p class="text-xs font-medium text-muted-foreground">
                    {{ $t('document.terms') }}
                </p>
                <p class="text-sm whitespace-pre-line">{{ terms }}</p>
            </div>
        </div>
    </div>
</template>
