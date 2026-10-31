<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { ref, shallowRef } from 'vue';
import TransactionFormDialog from '@/components/app/TransactionFormDialog.vue';
import { Button } from '@/components/ui/button';
import { options as optionsRoute } from '@/routes/transactions';
import type { TransactionFormOptions } from '@/types';

// Fetched the first time the dialog is opened, then kept for the session:
// the button sits on every page, so the options must not cost every response.
const options = shallowRef<TransactionFormOptions | null>(null);
const open = ref(false);
const loading = ref(false);

const label = wTrans('transaction.quick_new');

const openDialog = async () => {
    if (options.value !== null) {
        open.value = true;

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(optionsRoute.url(), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        options.value = (await response.json()) as TransactionFormOptions;
        open.value = true;
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Button
        size="sm"
        class="h-9 gap-1.5"
        :disabled="loading"
        :aria-label="label"
        @click="openDialog"
    >
        <Plus class="size-4" />
        <span class="hidden sm:inline">{{ label }}</span>
    </Button>

    <TransactionFormDialog
        v-if="options !== null"
        v-model:open="open"
        :options="options"
    />
</template>
