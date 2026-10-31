<script setup lang="ts">
import { Languages } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/composables/useLocale';

const { locale, availableLocales, updateLocale } = useLocale();

const triggerLabel = wTrans('locale.switch_label');
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="h-9 gap-1.5 px-2"
                :aria-label="triggerLabel"
            >
                <Languages class="size-[18px]" />
                <span class="text-xs font-medium">
                    {{
                        availableLocales.find((l) => l.value === locale)
                            ?.short ?? locale.toUpperCase()
                    }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem
                v-for="option in availableLocales"
                :key="option.value"
                class="cursor-pointer"
                :class="{ 'bg-accent': locale === option.value }"
                @select="updateLocale(option.value)"
            >
                <span
                    class="mr-2 w-6 text-xs font-medium text-muted-foreground"
                >
                    {{ option.short }}
                </span>
                {{ option.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
