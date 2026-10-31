<script setup lang="ts">
import { useLocale } from '@/composables/useLocale';

const { locale, availableLocales, updateLocale } = useLocale();
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg border border-border bg-surface-muted p-1"
    >
        <button
            v-for="option in availableLocales"
            :key="option.value"
            type="button"
            @click="updateLocale(option.value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 text-sm transition-colors',
                locale === option.value
                    ? 'bg-surface text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <span class="mr-2 text-xs font-medium opacity-60">
                {{ option.short }}
            </span>
            {{ option.label }}
        </button>
    </div>
</template>
