<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import MoneyInput from '@/components/app/MoneyInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { asRinggit } from '@/lib/money';
import { store, update } from '@/routes/projects';
import type { Client, EnumOption, Project } from '@/types';

/**
 * Used from both the project list and a project's own page, so a project can
 * be edited wherever the owner is looking at it.
 */
const props = defineProps<{
    project?: Project | null;
    clients: Client[];
    statuses: EnumOption[];
    /** Pre-selected when the dialog is opened from a client's page. */
    clientId?: number | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    client_id: 0,
    name: '',
    description: '',
    contract_amount: 0,
    status: 'prospect',
    start_date: '',
    end_date: '',
    internal_hourly_rate: 0,
});

// The form holds sen; the server is given ringgit.
form.transform((data) =>
    asRinggit(data, 'contract_amount', 'internal_hourly_rate'),
);

const reset = () => {
    const p = props.project;

    form.defaults({
        client_id: p?.client_id ?? props.clientId ?? props.clients[0]?.id ?? 0,
        name: p?.name ?? '',
        description: p?.description ?? '',
        contract_amount: p?.contract_amount ?? 0,
        status: p?.status ?? 'prospect',
        start_date: p?.start_date ?? '',
        end_date: p?.end_date ?? '',
        internal_hourly_rate: p?.internal_hourly_rate ?? 0,
    });
    form.reset();
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

const isEditing = computed(() => props.project != null);

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.project != null) {
        form.submit(update(props.project.id), options);

        return;
    }

    form.submit(store(), options);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? $t('project.edit') : $t('project.new') }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="project-client">{{ $t('client.one') }}</Label>
                    <Select v-model="form.client_id">
                        <SelectTrigger id="project-client">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="client in clients"
                                :key="client.id"
                                :value="client.id"
                            >
                                {{ client.display_name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.client_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-name">{{ $t('project.name') }}</Label>
                    <Input id="project-name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-description">
                        {{ $t('common.description') }}
                    </Label>
                    <Textarea
                        id="project-description"
                        v-model="form.description"
                        rows="2"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="project-contract">
                            {{ $t('project.contract_amount') }}
                        </Label>
                        <MoneyInput
                            id="project-contract"
                            v-model="form.contract_amount"
                        />
                        <InputError :message="form.errors.contract_amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="project-status">
                            {{ $t('common.status') }}
                        </Label>
                        <Select v-model="form.status">
                            <SelectTrigger id="project-status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in statuses"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="project-start">
                            {{ $t('project.start_date') }}
                        </Label>
                        <Input
                            id="project-start"
                            v-model="form.start_date"
                            type="date"
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="project-end">
                            {{ $t('project.end_date') }}
                        </Label>
                        <Input
                            id="project-end"
                            v-model="form.end_date"
                            type="date"
                        />
                        <InputError :message="form.errors.end_date" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="project-rate">
                        {{ $t('project.internal_hourly_rate') }}
                        <span class="text-muted-foreground">
                            ({{ $t('common.optional') }})
                        </span>
                    </Label>
                    <MoneyInput
                        id="project-rate"
                        v-model="form.internal_hourly_rate"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('project.internal_hourly_rate_hint') }}
                    </p>
                    <InputError :message="form.errors.internal_hourly_rate" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ $t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ $t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
