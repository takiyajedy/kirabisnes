<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { trans, wTrans } from 'laravel-vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const options: { value: Appearance; icon: typeof Sun; label: string }[] = [
    { value: 'light', icon: Sun, label: 'appearance.light' },
    { value: 'dark', icon: Moon, label: 'appearance.dark' },
    { value: 'system', icon: Monitor, label: 'appearance.system' },
];

const triggerLabel = wTrans('appearance.toggle_label');
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-9"
                :aria-label="triggerLabel"
            >
                <Monitor v-if="appearance === 'system'" class="size-[18px]" />
                <Moon
                    v-else-if="resolvedAppearance === 'dark'"
                    class="size-[18px]"
                />
                <Sun v-else class="size-[18px]" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                class="cursor-pointer"
                :class="{ 'bg-accent': appearance === option.value }"
                @select="updateAppearance(option.value)"
            >
                <component :is="option.icon" class="mr-2 size-4" />
                {{ trans(option.label) }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
