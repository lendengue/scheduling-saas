<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    Check,
    CircleCheck,
    Mail,
    Phone,
    UserX,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cancel, reschedule, status } from '@/routes/panel/appointments';
import type {
    AgendaStaff,
    AppointmentStatus,
    SelectedAppointment,
} from '@/types';

const props = defineProps<{
    appointment: SelectedAppointment | null;
    staff: AgendaStaff[];
    canChooseStaff: boolean;
}>();

const emit = defineEmits<{ changed: [] }>();

const open = defineModel<boolean>('open', { required: true });

type Panel = 'actions' | 'reschedule' | 'cancel';
const panel = ref<Panel>('actions');
const statusErrors = ref<Record<string, string>>({});
const savingStatus = ref<AppointmentStatus | null>(null);

const rescheduleForm = useForm({ date: '', time: '', staff_profile_id: 0 });
const cancelForm = useForm({ reason: '' });

watch(
    () => [open.value, props.appointment?.id],
    () => {
        if (!open.value || !props.appointment) {
            return;
        }

        panel.value = 'actions';
        statusErrors.value = {};
        rescheduleForm.clearErrors();
        rescheduleForm.date = props.appointment.local_start.slice(0, 10);
        rescheduleForm.time = props.appointment.local_start.slice(11, 16);
        rescheduleForm.staff_profile_id = props.appointment.staff_profile_id;
        cancelForm.reset();
        cancelForm.clearErrors();
    },
);

// Server errors use the request's field names (starts_at) or "appointment".
const rescheduleErrors = computed(
    () => rescheduleForm.errors as Record<string, string | undefined>,
);
const cancelErrors = computed(
    () => cancelForm.errors as Record<string, string | undefined>,
);

const statusVariant = computed(() => {
    switch (props.appointment?.status) {
        case 'pending':
            return 'outline';
        case 'cancelled':
        case 'no_show':
            return 'destructive';
        case 'completed':
            return 'secondary';
        default:
            return 'default';
    }
});

const canRecordOutcome = computed(
    () =>
        props.appointment?.can.update &&
        props.appointment.status === 'confirmed' &&
        props.appointment.has_started,
);

const when = computed(() => {
    if (!props.appointment) {
        return '';
    }

    const [date, time] = props.appointment.local_start.split('T');
    const day = new Date(`${date}T12:00:00`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    return `${day}, ${time} – ${props.appointment.local_service_end.slice(11, 16)}`;
});

const finish = () => {
    open.value = false;
    emit('changed');
};

const setStatus = (next: AppointmentStatus) => {
    if (!props.appointment) {
        return;
    }

    savingStatus.value = next;
    router.put(
        status(props.appointment.id),
        { status: next },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: finish,
            onError: (errors) => (statusErrors.value = errors),
            onFinish: () => (savingStatus.value = null),
        },
    );
};

const submitReschedule = () => {
    if (!props.appointment) {
        return;
    }

    rescheduleForm
        .transform(({ date, time, staff_profile_id }) => ({
            starts_at: `${date}T${time}`,
            staff_profile_id,
        }))
        .put(reschedule.url(props.appointment.id), {
            preserveScroll: true,
            onSuccess: finish,
        });
};

const submitCancel = () => {
    if (!props.appointment) {
        return;
    }

    cancelForm.post(cancel.url(props.appointment.id), {
        preserveScroll: true,
        onSuccess: finish,
    });
};
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <template v-if="appointment">
                <SheetHeader>
                    <div class="flex items-center gap-2">
                        <SheetTitle>{{ appointment.customer.name }}</SheetTitle>
                        <Badge :variant="statusVariant">
                            {{ appointment.status_label }}
                        </Badge>
                    </div>
                    <SheetDescription>
                        {{ appointment.service }} with {{ appointment.staff }}
                    </SheetDescription>
                </SheetHeader>

                <div class="flex flex-col gap-6 px-4 pb-6">
                    <dl class="grid gap-3 text-sm">
                        <div class="flex items-center gap-2">
                            <CalendarClock
                                class="size-4 text-muted-foreground"
                            />
                            <dd>{{ when }}</dd>
                        </div>
                        <div class="flex items-center gap-2">
                            <Mail class="size-4 text-muted-foreground" />
                            <dd>
                                <a
                                    :href="`mailto:${appointment.customer.email}`"
                                    class="underline-offset-4 hover:underline"
                                    >{{ appointment.customer.email }}</a
                                >
                            </dd>
                        </div>
                        <div
                            v-if="appointment.customer.phone"
                            class="flex items-center gap-2"
                        >
                            <Phone class="size-4 text-muted-foreground" />
                            <dd>
                                <a
                                    :href="`tel:${appointment.customer.phone}`"
                                    class="underline-offset-4 hover:underline"
                                    >{{ appointment.customer.phone }}</a
                                >
                            </dd>
                        </div>
                        <div class="text-muted-foreground">
                            {{ appointment.duration_minutes }} min ·
                            {{ appointment.price }}
                        </div>
                        <p
                            v-if="appointment.notes"
                            class="rounded-md bg-muted p-3 whitespace-pre-line"
                        >
                            {{ appointment.notes }}
                        </p>
                        <p
                            v-if="appointment.cancellation_reason"
                            class="text-muted-foreground"
                        >
                            Cancellation reason:
                            {{ appointment.cancellation_reason }}
                        </p>
                    </dl>

                    <template v-if="appointment.can.update">
                        <div
                            v-if="panel === 'actions'"
                            class="flex flex-col gap-2"
                        >
                            <Button
                                v-if="appointment.can.confirm"
                                :disabled="savingStatus !== null"
                                data-test="confirm-appointment"
                                @click="setStatus('confirmed')"
                            >
                                <Spinner v-if="savingStatus === 'confirmed'" />
                                <Check v-else />
                                Confirm
                            </Button>
                            <template v-if="canRecordOutcome">
                                <Button
                                    :disabled="savingStatus !== null"
                                    data-test="complete-appointment"
                                    @click="setStatus('completed')"
                                >
                                    <Spinner
                                        v-if="savingStatus === 'completed'"
                                    />
                                    <CircleCheck v-else />
                                    Mark as completed
                                </Button>
                                <Button
                                    variant="outline"
                                    :disabled="savingStatus !== null"
                                    data-test="no-show-appointment"
                                    @click="setStatus('no_show')"
                                >
                                    <Spinner
                                        v-if="savingStatus === 'no_show'"
                                    />
                                    <UserX v-else />
                                    Mark as no-show
                                </Button>
                            </template>
                            <Button
                                variant="outline"
                                @click="panel = 'reschedule'"
                            >
                                <CalendarClock />
                                Reschedule
                            </Button>
                            <Button
                                variant="ghost"
                                class="text-destructive hover:text-destructive"
                                @click="panel = 'cancel'"
                            >
                                <X />
                                Cancel appointment
                            </Button>
                            <InputError
                                :message="
                                    statusErrors.appointment ??
                                    statusErrors.status
                                "
                            />
                        </div>

                        <form
                            v-else-if="panel === 'reschedule'"
                            class="flex flex-col gap-4"
                            @submit.prevent="submitReschedule"
                        >
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-2">
                                    <Label for="reschedule-date">Date</Label>
                                    <Input
                                        id="reschedule-date"
                                        v-model="rescheduleForm.date"
                                        type="date"
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="reschedule-time">Time</Label>
                                    <Input
                                        id="reschedule-time"
                                        v-model="rescheduleForm.time"
                                        type="time"
                                        step="300"
                                        required
                                    />
                                </div>
                            </div>
                            <div v-if="canChooseStaff" class="grid gap-2">
                                <Label for="reschedule-staff"
                                    >Professional</Label
                                >
                                <NativeSelect
                                    id="reschedule-staff"
                                    v-model="rescheduleForm.staff_profile_id"
                                >
                                    <option
                                        v-for="member in staff"
                                        :key="member.id"
                                        :value="member.id"
                                    >
                                        {{ member.name }}
                                    </option>
                                </NativeSelect>
                            </div>
                            <InputError
                                :message="
                                    rescheduleErrors.starts_at ??
                                    rescheduleErrors.staff_profile_id ??
                                    rescheduleErrors.appointment
                                "
                            />
                            <p class="text-sm text-muted-foreground">
                                The customer is emailed the new time.
                            </p>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="panel = 'actions'"
                                >
                                    Back
                                </Button>
                                <Button
                                    type="submit"
                                    :disabled="rescheduleForm.processing"
                                >
                                    <Spinner v-if="rescheduleForm.processing" />
                                    Save new time
                                </Button>
                            </div>
                        </form>

                        <form
                            v-else
                            class="flex flex-col gap-4"
                            @submit.prevent="submitCancel"
                        >
                            <div class="grid gap-2">
                                <Label for="cancel-reason">
                                    Reason
                                    <span class="text-muted-foreground"
                                        >(optional, sent to the customer)</span
                                    >
                                </Label>
                                <Textarea
                                    id="cancel-reason"
                                    v-model="cancelForm.reason"
                                    rows="3"
                                />
                                <InputError
                                    :message="
                                        cancelErrors.reason ??
                                        cancelErrors.appointment
                                    "
                                />
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="panel = 'actions'"
                                >
                                    Back
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="cancelForm.processing"
                                    data-test="cancel-appointment"
                                >
                                    <Spinner v-if="cancelForm.processing" />
                                    Cancel appointment
                                </Button>
                            </div>
                        </form>
                    </template>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
