<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { wTrans } from 'laravel-vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import LocaleTabs from '@/components/LocaleTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'appearance.heading',
                href: edit(),
            },
        ],
    },
});

const head = wTrans('appearance.heading');
const heading = wTrans('appearance.heading');
const description = wTrans('appearance.description');
const themeHeading = wTrans('appearance.theme_heading');
const themeDescription = wTrans('appearance.theme_description');
const languageHeading = wTrans('appearance.language_heading');
const languageDescription = wTrans('appearance.language_description');
</script>

<template>
    <Head :title="head" />

    <h1 class="sr-only">{{ head }}</h1>

    <div class="space-y-10">
        <Heading variant="small" :title="heading" :description="description" />

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="themeHeading"
                :description="themeDescription"
            />
            <AppearanceTabs />
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="languageHeading"
                :description="languageDescription"
            />
            <LocaleTabs />
        </section>
    </div>
</template>
