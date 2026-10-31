<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { toRinggit, toSen } from '@/lib/money';
import { cn } from '@/lib/utils';

type Props = {
    /** The bound value is always an integer number of sen. */
    modelValue?: number | null;
    id?: string;
    name?: string;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    modelValue: 0,
    placeholder: '0.00',
});

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

// The user types ringgit; the form keeps sen.
const display = ref(props.modelValue ? toRinggit(props.modelValue) : '');

watch(
    () => props.modelValue,
    (sen) => {
        if (toSen(display.value) !== sen) {
            display.value = sen ? toRinggit(sen) : '';
        }
    },
);

const onInput = (event: Event) => {
    const raw = (event.target as HTMLInputElement).value;

    display.value = raw;
    emit('update:modelValue', toSen(raw));
};

const onBlur = () => {
    if (display.value.trim() === '') {
        emit('update:modelValue', 0);

        return;
    }

    display.value = toRinggit(toSen(display.value));
};

// A plain form posts what the user typed, in ringgit: the FormRequest is
// what turns it into sen (PLAN.md section 3.1).
const hiddenValue = computed(() => toRinggit(toSen(display.value)));
</script>

<template>
    <div class="relative">
        <span
            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground"
        >
            RM
        </span>
        <Input
            :id="id"
            :value="display"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :class="cn('pl-10 text-right tabular-nums', props.class)"
            @input="onInput"
            @blur="onBlur"
        />
        <!-- Posted in ringgit; the server converts it to sen. -->
        <input v-if="name" type="hidden" :name="name" :value="hiddenValue" />
    </div>
</template>
