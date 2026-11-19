<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CalendarPlus, CalendarX2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import BookingShell from '@/components/booking/BookingShell.vue';
import SlotPicker from '@/components/booking/SlotPicker.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cancel, reschedule } from '@/routes/appointments';
import { days, slots } from '@/routes/appointments/manage';
import type { AppointmentSummary, PublicBusiness, PublicSlot } from '@/types';

const props = defineProps<{
    business: PublicBusiness;
    appointment: AppointmentSummary;
    token: string;
    canChange: boolean;
    isActive: boolean;
    changeDeadline: string;
    cancellationNoticeHours: number;
    calendarUrl: string;
    today: string;
    maxDate: string;
}>();

type Mode = 'view' | 'reschedule' | 'cancel';
const mode = ref<Mode>('view');

const cancelForm = useForm({ token: props.token, reason: '' });
const rescheduleForm = useForm({
    token: props.token,
    starts_at: null as string | null,
});
const pendingSlot = ref<{ slot: PublicSlot; date: string } | null>(null);

const daysUrl = (month: string) =>
    days.url(props.appointment.id, { query: { token: props.token, month } });
const slotsUrl = (date: string) =>
    slots.url(props.appointment.id, { query: { token: props.token, date } });

const submitCancel = () =>
    cancelForm.post(cancel.url(props.appointment.id), {
        onSuccess: () => (mode.value = 'view'),
    });

const confirmReschedule = () => {
    rescheduleForm.starts_at = pendingSlot.value?.slot.starts_at ?? null;
    rescheduleForm.post(reschedule.url(props.appointment.id), {
        onSuccess: () => {
            mode.value = 'view';
            pendingSlot.value = null;
        },
    });
};

// Domain errors (window closed, time taken) come back as page errors.
const page = usePage();
const changeError = computed(
    () =>
        (page.props.errors as Record<string, string | undefined>).appointment ??
        rescheduleForm.errors.starts_at,
);

const formatDate = (value: string) =>
    new Date(`${value}T12:00:00Z`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    });
</script>

<template>
    <Head title="Your appointment" />

    <BookingShell :business="business">
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-xl font-semibold tracking-tight">
                    Your appointment
                </h1>
                <Badge
                    :variant="isActive ? 'secondary' : 'outline'"
                    data-test="appointment-status"
                >
                    {{ appointment.status_label }}
                </Badge>
            </div>

            <dl
                class="grid gap-3 rounded-lg border bg-background p-5 text-sm sm:grid-cols-2"
            >
                <div>
                    <dt class="text-muted-foreground">Service</dt>
                    <dd class="font-medium">{{ appointment.service }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">With</dt>
                    <dd class="font-medium">{{ appointment.staff }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">When</dt>
                    <dd class="font-medium capitalize">
                        {{ appointment.date }}<br />
                        {{ appointment.time }} – {{ appointment.ends_at_time }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Price</dt>
                    <dd class="font-medium">{{ appointment.price }}</dd>
                </div>
            </dl>

            <InputError :message="changeError" />

            <template v-if="isActive && mode === 'view'">
                <div v-if="canChange" class="flex flex-col gap-3 sm:flex-row">
                    <Button
                        data-test="reschedule-button"
                        @click="mode = 'reschedule'"
                    >
                        Reschedule
                    </Button>
                    <Button
                        variant="outline"
                        data-test="cancel-button"
                        @click="mode = 'cancel'"
                    >
                        <CalendarX2 /> Cancel appointment
                    </Button>
                    <Button as-child variant="ghost">
                        <a :href="calendarUrl">
                            <CalendarPlus /> Add to calendar
                        </a>
                    </Button>
                </div>
                <p
                    v-else
                    class="rounded-md border bg-background p-4 text-sm text-muted-foreground"
                >
                    Online changes close {{ cancellationNoticeHours }} hours
                    before the appointment. Please contact
                    {{ business.name }} directly.
                </p>
                <p v-if="canChange" class="text-xs text-muted-foreground">
                    You can change or cancel online until
                    {{ changeDeadline }}.
                </p>
            </template>

            <form
                v-if="mode === 'cancel'"
                class="flex flex-col gap-4 rounded-lg border bg-background p-5"
                @submit.prevent="submitCancel"
            >
                <div class="grid gap-2">
                    <Label for="cancel-reason">Reason (optional)</Label>
                    <Textarea
                        id="cancel-reason"
                        v-model="cancelForm.reason"
                        rows="2"
                        maxlength="500"
                    />
                    <InputError :message="cancelForm.errors.reason" />
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="mode = 'view'"
                    >
                        Keep appointment
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="cancelForm.processing"
                        data-test="confirm-cancel-button"
                    >
                        <Spinner v-if="cancelForm.processing" />
                        Cancel appointment
                    </Button>
                </div>
            </form>

            <section v-if="mode === 'reschedule'" class="flex flex-col gap-4">
                <div
                    v-if="pendingSlot"
                    class="flex flex-col gap-3 rounded-lg border bg-background p-5 text-sm"
                >
                    <p>
                        Move to
                        <strong class="capitalize">
                            {{ formatDate(pendingSlot.date) }} at
                            {{ pendingSlot.slot.label }}</strong
                        >?
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button variant="ghost" @click="pendingSlot = null">
                            Pick another time
                        </Button>
                        <Button
                            :disabled="rescheduleForm.processing"
                            data-test="confirm-reschedule-button"
                            @click="confirmReschedule"
                        >
                            <Spinner v-if="rescheduleForm.processing" />
                            Confirm new time
                        </Button>
                    </div>
                </div>
                <SlotPicker
                    v-else
                    :days-url="daysUrl"
                    :slots-url="slotsUrl"
                    :today="today"
                    :max-date="maxDate"
                    @pick="(slot, date) => (pendingSlot = { slot, date })"
                />
                <Button variant="ghost" @click="mode = 'view'">
                    Keep current time
                </Button>
            </section>
        </div>
    </BookingShell>
</template>
