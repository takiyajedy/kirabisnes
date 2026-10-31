<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, CheckCheck } from '@lucide/vue';
import { currentLocale, trans, wTrans } from 'laravel-vue-i18n';
import { computed, ref, shallowRef } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDate, formatDateTime } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import {
    index as notificationsIndex,
    readAll,
    read,
} from '@/routes/notifications';
import type { AppNotification } from '@/types';

/**
 * The bell in the top bar.
 *
 * Each row arrives as a translation key plus its parameters rather than a
 * finished sentence, so the text is built here — in whatever language is
 * active — and rewritten the moment the language is switched (PLAN.md 4).
 *
 * The list is fetched when the bell is opened. Only the unread count comes
 * with every page, because the bell is on every page and most visits will
 * never open it.
 */
const page = usePage();

const notifications = shallowRef<AppNotification[]>([]);
const open = ref(false);
const loading = ref(false);

const unread = computed(() => page.props.auth.unreadNotifications ?? 0);
const label = wTrans('nav.notifications');

const fetchList = async () => {
    loading.value = true;

    try {
        const response = await fetch(notificationsIndex.url(), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as {
            notifications: AppNotification[];
        };

        notifications.value = data.notifications;
    } finally {
        loading.value = false;
    }
};

const onOpenChange = (value: boolean) => {
    open.value = value;

    if (value) {
        void fetchList();
    }
};

/**
 * The sentence, built now rather than when the notification was written.
 * Amounts and dates are formatted here too, for the same reason.
 */
const textFor = (notification: AppNotification): string => {
    void currentLocale.value;

    const params: Record<string, string> = {};

    for (const [key, value] of Object.entries(notification.params)) {
        if (key === 'amount_sen') {
            params.amount = formatMoney(Number(value));

            continue;
        }

        params[key] =
            key === 'date' ? formatDate(String(value)) : String(value);
    }

    return trans(notification.key, params);
};

const toneClass = (tone: string) =>
    ({
        warning: 'bg-warning',
        expense: 'bg-expense',
        income: 'bg-income',
        info: 'bg-info',
    })[tone] ?? 'bg-info';

const openNotification = (notification: AppNotification) => {
    if (!notification.read) {
        router.post(read(notification.id).url, {}, { preserveScroll: true });
    }

    if (notification.url !== null) {
        router.visit(notification.url);
    }
};

const markAllRead = () => {
    router.post(
        readAll().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => void fetchList(),
        },
    );
};
</script>

<template>
    <DropdownMenu :open="open" @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative size-9"
                :aria-label="label"
            >
                <Bell class="size-[18px]" />
                <span
                    v-if="unread > 0"
                    class="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-expense text-[10px] font-medium text-expense-foreground tabular-nums"
                >
                    {{ unread > 9 ? '9+' : unread }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80">
            <DropdownMenuLabel class="flex items-center justify-between gap-2">
                <span>{{ label }}</span>
                <Button
                    v-if="unread > 0"
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1.5 text-xs font-normal"
                    @click.stop="markAllRead"
                >
                    <CheckCheck class="size-3.5" />
                    {{ $t('notification.mark_all_read') }}
                </Button>
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <p
                v-if="loading"
                class="px-2 py-6 text-center text-sm text-muted-foreground"
            >
                {{ $t('common.loading') }}
            </p>

            <p
                v-else-if="notifications.length === 0"
                class="px-2 py-6 text-center text-sm text-muted-foreground"
            >
                {{ $t('notification.empty') }}
            </p>

            <DropdownMenuItem
                v-for="notification in notifications"
                :key="notification.id"
                class="flex cursor-pointer items-start gap-2.5 py-2.5"
                @click="openNotification(notification)"
            >
                <span
                    class="mt-1.5 size-1.5 shrink-0 rounded-full"
                    :class="
                        notification.read
                            ? 'bg-transparent'
                            : toneClass(notification.tone)
                    "
                />
                <span class="min-w-0 space-y-0.5">
                    <span
                        class="block text-sm whitespace-normal"
                        :class="
                            notification.read ? 'text-muted-foreground' : ''
                        "
                    >
                        {{ textFor(notification) }}
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        {{ formatDateTime(notification.created_at) }}
                    </span>
                </span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
