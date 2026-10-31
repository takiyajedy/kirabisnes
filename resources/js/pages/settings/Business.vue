<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { wTrans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { edit, update } from '@/routes/business';

type BusinessSettings = {
    business_name: string;
    business_reg_no: string | null;
    business_email: string | null;
    business_phone: string | null;
    business_address: string | null;
    bank_details: string | null;
    invoice_footer_note: { ms: string | null; en: string | null } | null;
    invoice_prefix: string;
    quotation_prefix: string;
    default_payment_terms_days: number;
    default_document_locale: string;
    logo_path: string | null;
};

const props = defineProps<{
    settings: BusinessSettings;
    logoUrl: string | null;
    documentLocales: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'business.breadcrumb',
                href: edit(),
            },
        ],
    },
});

const form = useForm({
    business_name: props.settings.business_name ?? '',
    business_reg_no: props.settings.business_reg_no ?? '',
    business_email: props.settings.business_email ?? '',
    business_phone: props.settings.business_phone ?? '',
    business_address: props.settings.business_address ?? '',
    bank_details: props.settings.bank_details ?? '',
    invoice_footer_note: {
        ms: props.settings.invoice_footer_note?.ms ?? '',
        en: props.settings.invoice_footer_note?.en ?? '',
    },
    invoice_prefix: props.settings.invoice_prefix ?? 'INV',
    quotation_prefix: props.settings.quotation_prefix ?? 'QUO',
    default_payment_terms_days: props.settings.default_payment_terms_days ?? 14,
    default_document_locale: props.settings.default_document_locale ?? 'ms',
    logo: null as File | null,
    remove_logo: false as boolean,
});

const logoPreview = ref<string | null>(props.logoUrl);

const onLogoChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    form.logo = file;
    form.remove_logo = false;
    logoPreview.value =
        file === null ? props.logoUrl : URL.createObjectURL(file);
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
};

// Multipart, because of the logo upload.
const submit = () =>
    form.submit(update(), {
        preserveScroll: true,
        forceFormData: true,
    });

const head = wTrans('business.head');
const heading = wTrans('business.heading');
const description = wTrans('business.description');
const documentsHeading = wTrans('business.documents_heading');
const documentsDescription = wTrans('business.documents_description');
</script>

<template>
    <Head :title="head" />

    <h1 class="sr-only">{{ head }}</h1>

    <form class="space-y-10" @submit.prevent="submit">
        <section class="space-y-6">
            <Heading
                variant="small"
                :title="heading"
                :description="description"
            />

            <div class="grid gap-2">
                <Label for="business_name">{{ $t('business.name') }}</Label>
                <Input
                    id="business_name"
                    v-model="form.business_name"
                    required
                    :placeholder="$t('business.name_placeholder')"
                />
                <InputError :message="form.errors.business_name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="business_reg_no">
                        {{ $t('business.reg_no') }}
                    </Label>
                    <Input
                        id="business_reg_no"
                        v-model="form.business_reg_no"
                        :placeholder="$t('business.reg_no_placeholder')"
                    />
                    <InputError :message="form.errors.business_reg_no" />
                </div>

                <div class="grid gap-2">
                    <Label for="business_phone">
                        {{ $t('business.phone') }}
                    </Label>
                    <Input
                        id="business_phone"
                        v-model="form.business_phone"
                        placeholder="+60"
                    />
                    <InputError :message="form.errors.business_phone" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="business_email">{{ $t('business.email') }}</Label>
                <Input
                    id="business_email"
                    type="email"
                    v-model="form.business_email"
                    :placeholder="$t('login.email_placeholder')"
                />
                <InputError :message="form.errors.business_email" />
            </div>

            <div class="grid gap-2">
                <Label for="business_address">
                    {{ $t('business.address') }}
                </Label>
                <Textarea
                    id="business_address"
                    v-model="form.business_address"
                    rows="3"
                />
                <InputError :message="form.errors.business_address" />
            </div>

            <div class="grid gap-2">
                <Label for="logo">{{ $t('business.logo') }}</Label>
                <div class="flex items-center gap-4">
                    <div
                        v-if="logoPreview"
                        class="flex size-16 items-center justify-center overflow-hidden rounded-xl border border-border bg-surface"
                    >
                        <img
                            :src="logoPreview"
                            :alt="$t('business.logo')"
                            class="max-h-full max-w-full object-contain"
                        />
                    </div>
                    <div class="flex-1 space-y-2">
                        <Input
                            id="logo"
                            type="file"
                            accept="image/png,image/jpeg"
                            @change="onLogoChange"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('business.logo_hint') }}
                        </p>
                    </div>
                    <Button
                        v-if="logoPreview"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removeLogo"
                    >
                        {{ $t('business.logo_remove') }}
                    </Button>
                </div>
                <InputError :message="form.errors.logo" />
            </div>

            <div class="grid gap-2">
                <Label for="bank_details">
                    {{ $t('business.bank_details') }}
                </Label>
                <Textarea
                    id="bank_details"
                    v-model="form.bank_details"
                    rows="3"
                />
                <p class="text-xs text-muted-foreground">
                    {{ $t('business.bank_details_hint') }}
                </p>
                <InputError :message="form.errors.bank_details" />
            </div>
        </section>

        <section class="space-y-6">
            <Heading
                variant="small"
                :title="documentsHeading"
                :description="documentsDescription"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="invoice_prefix">
                        {{ $t('business.invoice_prefix') }}
                    </Label>
                    <Input
                        id="invoice_prefix"
                        v-model="form.invoice_prefix"
                        required
                        class="uppercase"
                    />
                    <InputError :message="form.errors.invoice_prefix" />
                </div>

                <div class="grid gap-2">
                    <Label for="quotation_prefix">
                        {{ $t('business.quotation_prefix') }}
                    </Label>
                    <Input
                        id="quotation_prefix"
                        v-model="form.quotation_prefix"
                        required
                        class="uppercase"
                    />
                    <InputError :message="form.errors.quotation_prefix" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="default_payment_terms_days">
                        {{ $t('business.payment_terms') }}
                    </Label>
                    <Input
                        id="default_payment_terms_days"
                        type="number"
                        min="0"
                        max="365"
                        v-model="form.default_payment_terms_days"
                        required
                        class="tabular-nums"
                    />
                    <InputError
                        :message="form.errors.default_payment_terms_days"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="default_document_locale">
                        {{ $t('business.default_document_locale') }}
                    </Label>
                    <Select v-model="form.default_document_locale">
                        <SelectTrigger id="default_document_locale">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in documentLocales"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="form.errors.default_document_locale"
                    />
                </div>
            </div>

            <div class="grid gap-2">
                <Label>{{ $t('business.invoice_footer_note') }}</Label>
                <p class="text-xs text-muted-foreground">
                    {{ $t('business.invoice_footer_note_hint') }}
                </p>
                <div class="grid gap-3">
                    <div class="grid gap-1.5">
                        <Label
                            for="invoice_footer_note_ms"
                            class="text-xs font-normal text-muted-foreground"
                        >
                            BM
                        </Label>
                        <Textarea
                            id="invoice_footer_note_ms"
                            v-model="form.invoice_footer_note.ms"
                            rows="2"
                        />
                        <InputError
                            :message="form.errors['invoice_footer_note.ms']"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label
                            for="invoice_footer_note_en"
                            class="text-xs font-normal text-muted-foreground"
                        >
                            EN
                        </Label>
                        <Textarea
                            id="invoice_footer_note_en"
                            v-model="form.invoice_footer_note.en"
                            rows="2"
                        />
                        <InputError
                            :message="form.errors['invoice_footer_note.en']"
                        />
                    </div>
                </div>
            </div>
        </section>

        <div class="flex items-center gap-4">
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="update-business-button"
            >
                {{ $t('common.save') }}
            </Button>
        </div>
    </form>
</template>
