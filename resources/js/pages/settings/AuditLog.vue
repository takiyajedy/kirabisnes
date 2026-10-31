<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileClock } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/audit-log';
import type { StatusToneValue } from '@/types';

type AuditRow = {
    id: number;
    event: string;
    type: string;
    subject: string | null;
    user_name: string | null;
    changes: Record<string, { from: unknown; to: unknown }> | null;
    created_at: string;
};

const props = defineProps<{
    logs: {
        data: AuditRow[];
        meta: {
            from: number | null;
            to: number | null;
            total: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    filters: { type: string | null; event: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'audit.title', href: index() }],
    },
});

const ALL = '__all__';

const filters = ref({
    type: props.filters.type ?? ALL,
    event: props.filters.event ?? ALL,
});

watch(
    filters,
    () => {
        const query: Record<string, string> = {};

        if (filters.value.type !== ALL) query.type = filters.value.type;
        if (filters.value.event !== ALL) query.event = filters.value.event;

        router.get(index().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { deep: true },
);

const toneFor = (event: string): StatusToneValue =>
    (
        {
            created: 'income',
            updated: 'info',
            deleted: 'expense',
        } as Record<string, StatusToneValue>
    )[event] ?? 'neutral';

/** A value as it reads in the trail; null is "nothing". */
const readable = (value: unknown) =>
    value === null || value === '' ? '—' : String(value);

const title = wTrans('audit.title');
</script>

<template>
    <Head :title="title" />

    <h1 class="sr-only">{{ title }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="title"
            :description="$t('audit.description')"
        />

        <div class="grid gap-3 sm:max-w-md sm:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="filter-type" class="text-xs text-muted-foreground">
                    {{ $t('audit.record') }}
                </Label>
                <Select v-model="filters.type">
                    <SelectTrigger id="filter-type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">
                            {{ $t('common.all') }}
                        </SelectItem>
                        <SelectItem value="transaction">
                            {{ $t('transaction.title') }}
                        </SelectItem>
                        <SelectItem value="invoice">
                            {{ $t('invoice.title') }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-event" class="text-xs text-muted-foreground">
                    {{ $t('audit.event') }}
                </Label>
                <Select v-model="filters.event">
                    <SelectTrigger id="filter-event">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">
                            {{ $t('common.all') }}
                        </SelectItem>
                        <SelectItem value="created">
                            {{ $t('audit.created') }}
                        </SelectItem>
                        <SelectItem value="updated">
                            {{ $t('audit.updated') }}
                        </SelectItem>
                        <SelectItem value="deleted">
                            {{ $t('audit.deleted') }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <EmptyState
            v-if="logs.data.length === 0"
            :title="$t('audit.empty')"
            :description="$t('audit.empty_description')"
            :icon="FileClock"
        />

        <ul
            v-else
            class="divide-y divide-border rounded-xl border border-border bg-surface"
        >
            <li
                v-for="log in logs.data"
                :key="log.id"
                class="space-y-2 px-4 py-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2">
                        <StatusBadge
                            :label="$t(`audit.${log.event}`)"
                            :tone="toneFor(log.event)"
                        />
                        <span class="truncate text-sm font-medium">
                            {{ log.subject ?? '—' }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ $t(`audit.record_${log.type}`) }}
                        </span>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        {{ formatDateTime(log.created_at) }}
                        <template v-if="log.user_name">
                            · {{ log.user_name }}
                        </template>
                    </p>
                </div>

                <dl
                    v-if="log.changes && Object.keys(log.changes).length > 0"
                    class="grid gap-1 text-xs sm:grid-cols-2"
                >
                    <div
                        v-for="(change, field) in log.changes"
                        :key="field"
                        class="flex flex-wrap items-baseline gap-1.5"
                    >
                        <dt class="text-muted-foreground">{{ field }}:</dt>
                        <dd class="flex items-baseline gap-1.5">
                            <span class="text-muted-foreground line-through">
                                {{ readable(change.from) }}
                            </span>
                            <span>→</span>
                            <span class="font-medium">
                                {{ readable(change.to) }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </li>
        </ul>

        <nav
            v-if="logs.meta.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">
                {{
                    $t('common.showing_results', {
                        from: String(logs.meta.from ?? 0),
                        to: String(logs.meta.to ?? 0),
                        total: String(logs.meta.total),
                    })
                }}
            </p>

            <div class="flex items-center gap-1">
                <template v-for="(link, i) in logs.meta.links" :key="i">
                    <span
                        v-if="link.url === null"
                        class="px-2.5 py-1 text-xs text-muted-foreground/60"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        :class="
                            cn(
                                'rounded-lg px-2.5 py-1 text-xs transition-colors',
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )
                        "
                        v-html="link.label"
                    />
                </template>
            </div>
        </nav>
    </div>
</template>
