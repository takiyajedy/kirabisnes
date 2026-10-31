<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { wTrans } from 'laravel-vue-i18n';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

// The auth layout translates these keys.
const authConfigKeys = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'twofactor.challenge_recovery_title',
            description: 'twofactor.challenge_recovery_description',
            buttonText: 'twofactor.challenge_recovery_toggle',
        };
    }

    return {
        title: 'twofactor.challenge_code_title',
        description: 'twofactor.challenge_code_description',
        buttonText: 'twofactor.challenge_code_toggle',
    };
});

const toggleLabel = computed(
    () => wTrans(authConfigKeys.value.buttonText).value,
);

watchEffect(() => {
    setLayoutProps({
        title: authConfigKeys.value.title,
        description: authConfigKeys.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const head = wTrans('twofactor.challenge_head');
</script>

<template>
    <Head :title="head" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <Button type="submit" class="w-full" :disabled="processing">
                    {{ $t('common.continue') }}
                </Button>
                <div class="text-center text-sm text-muted-foreground">
                    <span>{{ $t('twofactor.or_you_can') }} </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ toggleLabel }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    :placeholder="$t('twofactor.recovery_code_placeholder')"
                    v-focus
                    required
                />
                <InputError :message="errors.recovery_code" />
                <Button type="submit" class="w-full" :disabled="processing">
                    {{ $t('common.continue') }}
                </Button>

                <div class="text-center text-sm text-muted-foreground">
                    <span>{{ $t('twofactor.or_you_can') }} </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ toggleLabel }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
