<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import BusinessLogo from '@/components/BusinessLogo.vue';

const name = usePage().props.name;
</script>

<template>
    <BusinessLogo size="size-8" framed />

    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
