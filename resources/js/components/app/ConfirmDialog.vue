<script setup lang="ts">
import { wTrans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Props = {
    title?: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'destructive' | 'default';
    processing?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'destructive',
    processing: false,
});

const emit = defineEmits<{
    confirm: [];
}>();

const open = ref(false);

const defaultTitle = wTrans('confirm.title');
const defaultDescription = wTrans('confirm.description');
const defaultConfirm = wTrans('common.delete');
const defaultCancel = wTrans('common.cancel');

const onConfirm = () => {
    emit('confirm');
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot name="trigger" />
        </DialogTrigger>

        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>{{ title ?? defaultTitle }}</DialogTitle>
                <DialogDescription>
                    {{ description ?? defaultDescription }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">
                        {{ cancelLabel ?? defaultCancel }}
                    </Button>
                </DialogClose>

                <Button
                    :variant="props.variant"
                    :disabled="processing"
                    @click="onConfirm"
                >
                    {{ confirmLabel ?? defaultConfirm }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
