<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import Heading from '@/components/Heading.vue';
import PasskeyItem from '@/components/PasskeyItem.vue';
import PasskeyRegister from '@/components/PasskeyRegister.vue';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

withDefaults(defineProps<Props>(), {
    canManagePasskeys: false,
    passkeys: () => [],
});

const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
        preserveScroll: true,
        onError,
    });
};

const handleRegisterSuccess = () => {
    router.reload();
};

const heading = wTrans('passkey.heading');
const description = wTrans('passkey.description');
</script>

<template>
    <div v-if="canManagePasskeys" class="space-y-6">
        <Heading variant="small" :title="heading" :description="description" />

        <div class="overflow-hidden rounded-xl border border-border">
            <template v-if="passkeys.length">
                <PasskeyItem
                    v-for="passkey in passkeys"
                    :key="passkey.id"
                    :passkey="passkey"
                    @remove="handleDelete"
                />
            </template>

            <div v-else class="p-8 text-center">
                <div
                    class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-surface-muted"
                >
                    <KeyRound class="size-6 text-muted-foreground" />
                </div>
                <p class="font-medium">{{ $t('passkey.empty_title') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('passkey.empty_description') }}
                </p>
            </div>
        </div>

        <PasskeyRegister @success="handleRegisterSuccess" />
    </div>
</template>
