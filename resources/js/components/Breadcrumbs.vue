<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { currentLocale } from 'laravel-vue-i18n';
import { computed } from 'vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

const props = defineProps<Props>();

// Pages pass translation keys as titles; trans() returns the key if unknown.
const items = computed(() => {
    void currentLocale.value;

    return props.breadcrumbs.map((item) => ({
        ...item,
        label: trans(item.title),
    }));
});
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in items" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === items.length - 1">
                        <BreadcrumbPage>{{ item.label }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href">{{ item.label }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== items.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
