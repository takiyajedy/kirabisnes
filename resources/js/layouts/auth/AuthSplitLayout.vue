<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { wTrans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import BusinessLogo from '@/components/BusinessLogo.vue';
import { home } from '@/routes';

const page = usePage();
const name = computed(() => page.props.name);

const props = defineProps<{
    title?: string;
    description?: string;
}>();

const title = computed(() => (props.title ? wTrans(props.title).value : ''));
const description = computed(() =>
    props.description ? wTrans(props.description).value : '',
);
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col bg-muted p-10 text-white lg:flex dark:border-r"
        >
            <div class="absolute inset-0 bg-zinc-900" />
            <Link
                :href="home()"
                class="relative z-20 flex items-center text-lg font-medium"
            >
                <BusinessLogo
                    size="size-8"
                    icon-class="text-white"
                    class="mr-2"
                />
                {{ name }}
            </Link>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
