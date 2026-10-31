<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Play, Plus, Square, Timer, Trash2 } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
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
import { formatDuration, formatHours } from '@/lib/duration';
import { formatDateTime } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { destroy, start, stop, store, update } from '@/routes/time';
import type { Project, ProjectProfit, TimeEntry } from '@/types';

/**
 * The time logged against a project, and the timer for it.
 *
 * The labour cost sits here rather than only in the report, because the
 * question "was this job worth it" is asked while looking at the job.
 */
const props = defineProps<{
    project: Project;
    profit: ProjectProfit;
    entries: TimeEntry[];
}>();

const page = usePage();

const runningHere = computed(
    () => page.props.runningTimer?.project_id === props.project.id,
);
const runningElsewhere = computed(
    () =>
        page.props.runningTimer !== null &&
        page.props.runningTimer?.project_id !== props.project.id,
);

const startTimer = () => {
    router.post(start(props.project.id).url, {}, { preserveScroll: true });
};

const stopTimer = () => {
    router.post(stop().url, {}, { preserveScroll: true });
};

// --- logging time by hand ------------------------------------------------
const open = ref(false);
const editing = ref<TimeEntry | null>(null);

const localNow = () => {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 16);
};

const form = useForm({
    project_id: props.project.id,
    started_at: localNow(),
    minutes: 60,
    note: '',
    billable: true,
});

const load = (entry: TimeEntry | null) => {
    editing.value = entry;
    form.defaults({
        project_id: props.project.id,
        started_at: entry?.started_at.slice(0, 16) ?? localNow(),
        minutes: entry?.minutes ?? 60,
        note: entry?.note ?? '',
        billable: entry?.billable ?? true,
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

const remove = (entry: TimeEntry) => {
    router.delete(destroy(entry.id).url, { preserveScroll: true });
};

const billableLabel = wTrans('time.billable');
</script>

<template>
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <h2 class="text-sm font-medium">{{ $t('time.title') }}</h2>
                <p class="text-xs text-muted-foreground">
                    {{
                        $t('time.summary', {
                            billable: formatHours(profit.minutes),
                            total: formatHours(profit.minutes_total),
                        })
                    }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="runningHere"
                    variant="secondary"
                    size="sm"
                    class="gap-1.5"
                    @click="stopTimer"
                >
                    <Square class="size-3.5" />
                    {{ $t('time.stop') }}
                </Button>
                <Button
                    v-else
                    variant="secondary"
                    size="sm"
                    class="gap-1.5"
                    :title="
                        runningElsewhere ? $t('time.will_switch') : undefined
                    "
                    @click="startTimer"
                >
                    <Play class="size-3.5" />
                    {{ $t('time.start') }}
                </Button>

                <Button size="sm" class="gap-1.5" @click="load(null)">
                    <Plus class="size-3.5" />
                    {{ $t('time.log') }}
                </Button>
            </div>
        </div>

        <p v-if="runningElsewhere" class="text-xs text-muted-foreground">
            {{
                $t('time.running_elsewhere', {
                    project: page.props.runningTimer?.project_name ?? '',
                })
            }}
        </p>

        <!-- What the logged time has cost -->
        <div
            v-if="profit.hourly_rate > 0"
            class="grid gap-4 rounded-xl border border-border bg-surface-muted p-4 sm:grid-cols-3"
        >
            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('project.internal_hourly_rate') }}
                </p>
                <p class="text-sm font-medium tabular-nums">
                    {{ formatMoney(profit.hourly_rate) }}
                </p>
            </div>
            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('report.labour') }}
                </p>
                <p class="text-sm font-medium tabular-nums">
                    {{ formatMoney(profit.labour) }}
                </p>
            </div>
            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">
                    {{ $t('report.profit_after_labour') }}
                </p>
                <p
                    class="text-sm font-medium tabular-nums"
                    :class="
                        profit.profit_after_labour < 0
                            ? 'text-expense'
                            : 'text-income'
                    "
                >
                    {{ formatMoney(profit.profit_after_labour) }}
                </p>
            </div>
        </div>

        <p
            v-else
            class="rounded-xl border border-border bg-surface-muted p-4 text-xs text-muted-foreground"
        >
            {{ $t('time.no_rate') }}
        </p>

        <p
            v-if="entries.length === 0"
            class="rounded-xl border border-border bg-surface px-4 py-6 text-center text-sm text-muted-foreground"
        >
            {{ $t('time.empty') }}
        </p>

        <ul
            v-else
            class="divide-y divide-border rounded-xl border border-border bg-surface"
        >
            <li
                v-for="entry in entries"
                :key="entry.id"
                class="flex items-center justify-between gap-4 px-4 py-3"
            >
                <div class="min-w-0 space-y-0.5">
                    <p class="flex items-center gap-1.5 text-sm font-medium">
                        <Timer
                            v-if="entry.is_running"
                            class="size-3.5 shrink-0 animate-pulse text-income"
                        />
                        {{ formatDuration(entry.elapsed_minutes) }}
                        <span
                            v-if="!entry.billable"
                            class="text-xs font-normal text-muted-foreground"
                        >
                            · {{ $t('time.not_billable') }}
                        </span>
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ formatDateTime(entry.started_at) }}
                        <template v-if="entry.note"
                            >· {{ entry.note }}</template
                        >
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        v-if="!entry.is_running"
                        variant="ghost"
                        size="sm"
                        @click="load(entry)"
                    >
                        <Pencil class="size-3.5" />
                        <span class="sr-only">{{ $t('common.edit') }}</span>
                    </Button>

                    <ConfirmDialog
                        :title="$t('time.delete_confirm')"
                        :description="$t('time.delete_description')"
                        @confirm="remove(entry)"
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
            </li>
        </ul>
    </section>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? $t('time.edit') : $t('time.log') }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="started_at">{{ $t('time.started_at') }}</Label>
                    <Input
                        id="started_at"
                        v-model="form.started_at"
                        type="datetime-local"
                        required
                    />
                    <InputError :message="form.errors.started_at" />
                </div>

                <div class="grid gap-2">
                    <Label for="minutes">{{ $t('time.minutes') }}</Label>
                    <Input
                        id="minutes"
                        v-model="form.minutes"
                        type="number"
                        min="1"
                        max="10080"
                        class="tabular-nums"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ formatDuration(Number(form.minutes) || 0) }}
                    </p>
                    <InputError :message="form.errors.minutes" />
                </div>

                <div class="grid gap-2">
                    <Label for="note">
                        {{ $t('common.notes') }}
                        <span class="text-muted-foreground">
                            ({{ $t('common.optional') }})
                        </span>
                    </Label>
                    <Input id="note" v-model="form.note" />
                    <InputError :message="form.errors.note" />
                </div>

                <Label
                    for="billable"
                    class="flex items-start gap-3 font-normal"
                >
                    <Checkbox
                        id="billable"
                        v-model="form.billable"
                        class="mt-0.5"
                    />
                    <span class="space-y-0.5">
                        <span class="block text-sm">{{ billableLabel }}</span>
                        <span class="block text-xs text-muted-foreground">
                            {{ $t('time.billable_hint') }}
                        </span>
                    </span>
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
