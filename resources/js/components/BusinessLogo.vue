<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

/**
 * The mark that stands for the business.
 *
 * It is the uploaded logo when there is one, and the built-in icon when
 * there is not. One component so the sidebar and the sign-in pages cannot
 * end up showing different things.
 *
 * An uploaded logo is shown on its own: most logos carry their own shape and
 * colour, and sitting one on the app's emerald tile would fight with it. The
 * box keeps its size either way, so nothing shifts when a logo is added.
 */
const props = withDefaults(
    defineProps<{
        /** Tailwind size classes for the box, e.g. "size-8". */
        size?: string;
        /** Put the built-in icon on the app's tile. */
        framed?: boolean;
        /** Classes for the built-in icon only. */
        iconClass?: string;
    }>(),
    {
        size: 'size-8',
        framed: false,
        iconClass: '',
    },
);

const page = usePage();

const logo = computed(() => page.props.logo ?? null);
const name = computed(() => page.props.name);
</script>

<template>
    <img
        v-if="logo !== null"
        :src="logo"
        :alt="name"
        :class="cn('shrink-0 rounded-md object-contain', props.size)"
    />

    <div
        v-else-if="framed"
        :class="
            cn(
                'flex shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground',
                props.size,
            )
        "
    >
        <AppLogoIcon
            :class="
                cn('size-5 fill-current text-white dark:text-black', iconClass)
            "
        />
    </div>

    <AppLogoIcon
        v-else
        :class="
            cn('shrink-0 fill-current text-foreground', props.size, iconClass)
        "
    />
</template>
