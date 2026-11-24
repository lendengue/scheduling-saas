<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { maskBrazilianPhone } from '@/composables/usePhoneMask';
import { store } from '@/routes/panel/appointments';
import type { AgendaService, AgendaStaff } from '@/types';

const props = defineProps<{
    staff: AgendaStaff[];
    services: AgendaService[];
    /** "YYYY-MM-DDTHH:mm" in the business timezone. */
    startsAt: string;
    staffProfileId: number | null;
}>();

const emit = defineEmits<{ booked: [] }>();

const open = defineModel<boolean>('open', { required: true });

const activeServices = computed(() =>
    props.services.filter((service) => service.is_active),
);

const form = useForm({
    service_id: null as number | null,
    staff_profile_id: null as number | null,
    date: '',
    time: '',
    name: '',
    email: '',
    phone: '',
    notes: '',
});

watch(
    () => form.phone,
    (value) => (form.phone = maskBrazilianPhone(value)),
);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.defaults({
        service_id: activeServices.value[0]?.id ?? null,
        staff_profile_id: props.staffProfileId ?? props.staff[0]?.id ?? null,
        date: props.startsAt.slice(0, 10),
        time: props.startsAt.slice(11, 16),
        name: '',
        email: '',
        phone: '',
        notes: '',
    });
    form.reset();
});

// starts_at is built from the date and time fields on submit.
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const submit = () => {
    form.transform(({ date, time, ...data }) => ({
        ...data,
        starts_at: `${date}T${time}`,
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            emit('booked');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>New appointment</DialogTitle>
                    <DialogDescription>
                        Book a walk-in or a phone booking. The customer gets a
                        confirmation email with their management link.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="appointment-service">Service</Label>
                        <NativeSelect
                            id="appointment-service"
                            v-model="form.service_id"
                            required
                        >
                            <option
                                v-for="service in activeServices"
                                :key="service.id"
                                :value="service.id"
                            >
                                {{ service.name }} ({{
                                    service.duration_minutes
                                }}
                                min)
                            </option>
                        </NativeSelect>
                        <InputError :message="form.errors.service_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="appointment-staff">Professional</Label>
                        <NativeSelect
                            id="appointment-staff"
                            v-model="form.staff_profile_id"
                            :disabled="staff.length === 1"
                            required
                        >
                            <option
                                v-for="member in staff"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }}
                            </option>
                        </NativeSelect>
                        <InputError :message="form.errors.staff_profile_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="appointment-date">Date</Label>
                        <Input
                            id="appointment-date"
                            v-model="form.date"
                            type="date"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="appointment-time">Time</Label>
                        <Input
                            id="appointment-time"
                            v-model="form.time"
                            type="time"
                            step="300"
                            required
                        />
                    </div>
                </div>
                <InputError :message="errors.starts_at" />

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="appointment-name">Customer name</Label>
                        <Input
                            id="appointment-name"
                            v-model="form.name"
                            autocomplete="off"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="appointment-email">Email</Label>
                            <Input
                                id="appointment-email"
                                v-model="form.email"
                                type="email"
                                autocomplete="off"
                                required
                            />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="appointment-phone">
                                Phone
                                <span class="text-muted-foreground"
                                    >(optional)</span
                                >
                            </Label>
                            <Input
                                id="appointment-phone"
                                v-model="form.phone"
                                type="tel"
                                autocomplete="off"
                                placeholder="(11) 98765-4321"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="appointment-notes">
                            Notes
                            <span class="text-muted-foreground"
                                >(optional)</span
                            >
                        </Label>
                        <Textarea
                            id="appointment-notes"
                            v-model="form.notes"
                            rows="2"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Book appointment
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
