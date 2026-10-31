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
//
// Bound with v-model, not :value. Input declares no `value` prop, so :value
// would arrive as a plain attribute and Input's own v-model would overwrite
// it on mount — every amount already on the record would render as an empty
// field, and typing into what looked blank would quietly replace it.
const display = ref(props.modelValue ? toRinggit(props.modelValue) : '');

watch(
    () => props.modelValue,
    (sen) => {
        if (toSen(display.value) !== sen) {
            display.value = sen ? toRinggit(sen) : '';
        }
    },
);

watch(display, (raw) => {
    emit('update:modelValue', toSen(raw));
});

/**
 * Typing replaces the amount instead of appending to it.
 *
 * Without this, a field reading 150.00 takes a 5 as 150.005. Nobody edits a
 * price by appending digits to the one already there.
 */
const onFocus = (event: FocusEvent) => {
    (event.target as HTMLInputElement).select();
};

const onBlur = () => {
    const sen = toSen(display.value);

    // Zero is left blank so the placeholder shows and the next thing typed
    // starts a fresh number. A literal "0.00" sitting in the field turns a
    // 5 into RM 0.05.
    display.value = sen === 0 ? '' : toRinggit(sen);

    emit('update:modelValue', sen);
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
            v-model="display"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :class="cn('pl-10 text-right tabular-nums', props.class)"
            @focus="onFocus"
            @blur="onBlur"
        />
        <!-- Posted in ringgit; the server converts it to sen. -->
        <input v-if="name" type="hidden" :name="name" :value="hiddenValue" />
    </div>
</template>
