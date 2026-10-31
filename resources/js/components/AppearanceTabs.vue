<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'appearance.light' },
    { value: 'dark', Icon: Moon, label: 'appearance.dark' },
    { value: 'system', Icon: Monitor, label: 'appearance.system' },
] as const;
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg border border-border bg-surface-muted p-1"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 text-sm transition-colors',
                appearance === value
                    ? 'bg-surface text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 size-4" />
            <span class="ml-1.5">{{ trans(label) }}</span>
        </button>
    </div>
</template>
