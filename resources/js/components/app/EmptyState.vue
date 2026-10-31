<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { Component } from 'vue';

type Props = {
    title: string;
    description?: string;
    icon?: Component;
};

withDefaults(defineProps<Props>(), {
    icon: () => Inbox,
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-xl border border-border bg-surface px-6 py-14 text-center"
    >
        <div
            class="flex size-11 items-center justify-center rounded-xl bg-surface-muted"
        >
            <component :is="icon" class="size-[18px] text-muted-foreground" />
        </div>

        <div class="space-y-1">
            <p class="font-medium">{{ title }}</p>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.action" class="mt-1">
            <slot name="action" />
        </div>
    </div>
</template>
