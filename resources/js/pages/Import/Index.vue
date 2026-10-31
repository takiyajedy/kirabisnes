<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, FileUp, Link2, Upload, XCircle } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatMoney } from '@/lib/money';
import {
    destroy,
    index,
    // Aliased: the page has a prop called `preview` too.
    preview as previewRoute,
    store,
} from '@/routes/import';
import type { Account, EnumOption, StatusToneValue } from '@/types';

type PreviewRow = {
    row: number;
    date: string | null;
    description: string;
    amount: number;
    type: string;
    status: 'new' | 'matched' | 'skipped';
    matched_id: number | null;
    error: string | null;
};

type Preview = {
    account_id: number;
    account_name: string;
    columns: EnumOption[];
    rows: PreviewRow[];
    counts: { new: number; matched: number; skipped: number };
};

const props = defineProps<{
    accounts: Account[];
    dateFormats: EnumOption[];
    matchDays: number;
    preview: Preview | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'import.title', href: index() }],
    },
});

const title = wTrans('import.title');

// --- step one: the file and which column is which -----------------------
const form = useForm({
    statement: null as File | null,
    account_id: props.accounts[0]?.id ?? 0,
    has_headers: true,
    date_format: 'auto',
    date_column: 0,
    description_column: 1,
    amount_column: 2,
    credit_column: '',
});

const onFileChange = (event: Event) => {
    form.statement = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const submitPreview = () => {
    form.submit(previewRoute(), { forceFormData: true, preserveScroll: true });
};

// --- step two: which rows to keep ---------------------------------------
const chosen = ref<number[]>([]);

watch(
    () => props.preview,
    (value) => {
        // Everything new is ticked to begin with: that is what the owner
        // came to do, and unticking is easier than hunting.
        chosen.value =
            value?.rows
                .filter((row) => row.status === 'new')
                .map((r) => r.row) ?? [];
    },
    { immediate: true },
);

const toggle = (row: PreviewRow) => {
    chosen.value = chosen.value.includes(row.row)
        ? chosen.value.filter((n) => n !== row.row)
        : [...chosen.value, row.row];
};

const confirm = () => {
    router.post(store().url, { rows: chosen.value }, { preserveScroll: true });
};

const startOver = () => {
    router.delete(destroy().url);
};

const toneFor = (status: string): StatusToneValue =>
    (
        {
            new: 'income',
            matched: 'neutral',
            skipped: 'warning',
        } as Record<string, StatusToneValue>
    )[status] ?? 'neutral';
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="title" :description="$t('import.description')" />

        <!-- Step one -->
        <form
            v-if="preview === null"
            class="space-y-5 rounded-xl border border-border bg-surface p-5"
            @submit.prevent="submitPreview"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="statement">{{ $t('import.file') }}</Label>
                    <Input
                        id="statement"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        required
                        @change="onFileChange"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('import.file_hint') }}
                    </p>
                    <InputError :message="form.errors.statement" />
                </div>

                <div class="grid gap-2">
                    <Label for="account_id">{{
                        $t('transaction.account')
                    }}</Label>
                    <Select v-model="form.account_id">
                        <SelectTrigger id="account_id">
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
                    <InputError :message="form.errors.account_id" />
                </div>
            </div>

            <Label
                for="has_headers"
                class="flex items-center gap-3 font-normal"
            >
                <Checkbox id="has_headers" v-model="form.has_headers" />
                <span>{{ $t('import.has_headers') }}</span>
            </Label>

            <!-- Which column is which -->
            <div class="space-y-3">
                <p class="text-sm font-medium">{{ $t('import.columns') }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ $t('import.columns_hint') }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="date_column">{{ $t('common.date') }}</Label>
                        <Input
                            id="date_column"
                            v-model="form.date_column"
                            type="number"
                            min="0"
                            class="tabular-nums"
                        />
                        <InputError :message="form.errors.date_column" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description_column">
                            {{ $t('common.description') }}
                        </Label>
                        <Input
                            id="description_column"
                            v-model="form.description_column"
                            type="number"
                            min="0"
                            class="tabular-nums"
                        />
                        <InputError :message="form.errors.description_column" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="amount_column">
                            {{ $t('import.amount_column') }}
                        </Label>
                        <Input
                            id="amount_column"
                            v-model="form.amount_column"
                            type="number"
                            min="0"
                            class="tabular-nums"
                        />
                        <InputError :message="form.errors.amount_column" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="credit_column">
                            {{ $t('import.credit_column') }}
                        </Label>
                        <Input
                            id="credit_column"
                            v-model="form.credit_column"
                            type="number"
                            min="0"
                            class="tabular-nums"
                            :placeholder="$t('common.none')"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('import.credit_column_hint') }}
                        </p>
                        <InputError :message="form.errors.credit_column" />
                    </div>
                </div>

                <div class="grid max-w-xs gap-2">
                    <Label for="date_format">{{
                        $t('import.date_format_label')
                    }}</Label>
                    <Select v-model="form.date_format">
                        <SelectTrigger id="date_format">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in dateFormats"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.date_format" />
                </div>
            </div>

            <Button type="submit" class="gap-1.5" :disabled="form.processing">
                <Upload class="size-4" />
                {{ $t('import.read_file') }}
            </Button>
        </form>

        <!-- Step two -->
        <template v-else>
            <div
                class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-surface p-5"
            >
                <div class="space-y-1">
                    <p class="text-sm font-medium">
                        {{ preview.account_name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t('import.summary', {
                                new: String(preview.counts.new),
                                matched: String(preview.counts.matched),
                                skipped: String(preview.counts.skipped),
                            })
                        }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="ghost" @click="startOver">
                        {{ $t('import.start_over') }}
                    </Button>
                    <Button
                        class="gap-1.5"
                        :disabled="chosen.length === 0"
                        @click="confirm"
                    >
                        <FileUp class="size-4" />
                        {{
                            $t('import.import_count', {
                                count: String(chosen.length),
                            })
                        }}
                    </Button>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ $t('import.match_hint', { days: String(matchDays) }) }}
            </p>

            <div
                class="overflow-x-auto rounded-xl border border-border bg-surface"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="w-10 px-4 py-2.5"></th>
                            <th
                                class="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground"
                            >
                                {{ $t('common.date') }}
                            </th>
                            <th
                                class="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground"
                            >
                                {{ $t('common.description') }}
                            </th>
                            <th
                                class="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground"
                            >
                                {{ $t('common.amount') }}
                            </th>
                            <th
                                class="w-32 px-4 py-2.5 text-left text-xs font-medium text-muted-foreground"
                            >
                                {{ $t('common.status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in preview.rows"
                            :key="row.row"
                            class="border-b border-border last:border-0"
                            :class="row.status !== 'new' ? 'opacity-70' : ''"
                        >
                            <td class="px-4 py-2.5">
                                <Checkbox
                                    v-if="row.status === 'new'"
                                    :model-value="chosen.includes(row.row)"
                                    :aria-label="row.description"
                                    @update:model-value="toggle(row)"
                                />
                                <CheckCircle2
                                    v-else-if="row.status === 'matched'"
                                    class="size-4 text-muted-foreground"
                                />
                                <XCircle v-else class="size-4 text-warning" />
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                {{ row.date ? formatDate(row.date) : '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <p class="truncate">
                                    {{ row.description || '—' }}
                                </p>
                                <p
                                    v-if="row.error"
                                    class="text-xs text-warning"
                                >
                                    {{ row.error }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums"
                                :class="
                                    row.type === 'income'
                                        ? 'text-income'
                                        : 'text-expense'
                                "
                            >
                                {{ row.type === 'income' ? '' : '−'
                                }}{{ formatMoney(row.amount) }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="flex items-center gap-1.5">
                                    <Link2
                                        v-if="row.status === 'matched'"
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    <StatusBadge
                                        :label="
                                            $t(`import.status.${row.status}`)
                                        "
                                        :tone="toneFor(row.status)"
                                    />
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
