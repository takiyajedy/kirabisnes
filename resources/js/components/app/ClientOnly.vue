<script setup lang="ts">
import { onMounted, ref } from 'vue';

/**
 * Renders its content only in the browser. Server-side rendering is on
 * (PLAN.md section 2), and anything that needs a canvas or a live measurement
 * has nothing useful to produce on the server — the fallback slot holds the
 * space so the layout does not jump when the real thing arrives.
 */
const mounted = ref(false);

onMounted(() => {
    mounted.value = true;
});
</script>

<template>
    <slot v-if="mounted" />
    <slot v-else name="fallback" />
</template>
