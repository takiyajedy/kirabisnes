<script setup lang="ts">
import { Search } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import LanguageSwitcher from '@/components/app/LanguageSwitcher.vue';
import NotificationBell from '@/components/app/NotificationBell.vue';
import QuickTransactionButton from '@/components/app/QuickTransactionButton.vue';
import ThemeToggle from '@/components/app/ThemeToggle.vue';
import TimerWidget from '@/components/app/TimerWidget.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const searchPlaceholder = wTrans('common.search_placeholder');
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="ml-auto flex items-center gap-1.5">
            <div class="relative hidden lg:block">
                <Search
                    class="pointer-events-none absolute inset-y-0 left-2.5 my-auto size-4 text-muted-foreground"
                />
                <Input
                    type="search"
                    class="h-9 w-48 pl-8"
                    :placeholder="searchPlaceholder"
                    disabled
                />
            </div>

            <TimerWidget />

            <QuickTransactionButton />

            <LanguageSwitcher />
            <ThemeToggle />

            <NotificationBell />
        </div>
    </header>
</template>
