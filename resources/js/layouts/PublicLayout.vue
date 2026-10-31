<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';

/**
 * The frame for a page a client sees without logging in (PLAN.md phase 6).
 *
 * No sidebar and no user menu: the visitor is not the owner and has nothing
 * to navigate to. The theme follows the visitor's own system setting, which
 * the inline script in the Blade root already applies — there is nothing to
 * save for someone with no account.
 */
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <slot />
    </div>
    <Toaster />
</template>
