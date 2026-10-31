<script setup lang="ts">
import { KeyRound, Trash2 } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Passkey } from '@/types/auth';

const props = defineProps<{
    passkey: Passkey;
}>();

const emit = defineEmits<{
    remove: [id: number, onError: () => void];
}>();

const isDeleting = ref(false);

const handleDelete = () => {
    isDeleting.value = true;
    emit('remove', props.passkey.id, () => {
        isDeleting.value = false;
    });
};

const addedLabel = computed(
    () =>
        wTrans('passkey.added_at', { time: props.passkey.created_at_diff })
            .value,
);
const lastUsedLabel = computed(() =>
    props.passkey.last_used_at_diff
        ? wTrans('passkey.last_used_at', {
              time: props.passkey.last_used_at_diff,
          }).value
        : '',
);
const removeDescription = computed(
    () =>
        wTrans('passkey.remove_description', { name: props.passkey.name })
            .value,
);
</script>

<template>
    <div class="flex items-center justify-between border-b p-4 last:border-b-0">
        <div class="flex items-center gap-4">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface-muted"
            >
                <KeyRound class="size-[18px] text-muted-foreground" />
            </div>
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <p class="font-medium tracking-tight">{{ passkey.name }}</p>
                    <span
                        v-if="passkey.authenticator"
                        class="inline-flex items-center gap-1 rounded-md bg-surface-muted px-2 py-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset"
                    >
                        {{ passkey.authenticator }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ addedLabel }}
                    <template v-if="lastUsedLabel">
                        <span class="mx-1 text-muted-foreground/50">/</span>
                        {{ lastUsedLabel }}
                    </template>
                </p>
            </div>
        </div>

        <Dialog>
            <DialogTrigger as-child>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                >
                    <Trash2 class="size-4" />
                    <span class="sr-only">{{ $t('passkey.remove') }}</span>
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>{{ $t('passkey.remove_title') }}</DialogTitle>
                <DialogDescription>{{ removeDescription }}</DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('common.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="isDeleting"
                        @click="handleDelete"
                    >
                        {{
                            isDeleting
                                ? $t('passkey.removing')
                                : $t('passkey.remove_title')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
