<script setup lang="ts">
import FullCalendar from '@fullcalendar/vue3';
import type {
    CalendarOptions,
    DateSelectInfo,
    EventClickInfo,
    EventDropInfo,
    EventInput,
    EventSourceFuncInfo,
} from '@fullcalendar/vue3';
import interactionPlugin from '@fullcalendar/vue3/interaction';
import listPlugin from '@fullcalendar/vue3/list';
import classicThemePlugin from '@fullcalendar/vue3/themes/classic';
import timeGridPlugin from '@fullcalendar/vue3/timegrid';
import '@fullcalendar/vue3/skeleton.css';
import '@fullcalendar/vue3/themes/classic/theme.css';
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, useTemplateRef, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppointmentSheet from '@/components/agenda/AppointmentSheet.vue';
import NewAppointmentDialog from '@/components/agenda/NewAppointmentDialog.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { agenda } from '@/routes';
import { events as eventsRoute } from '@/routes/agenda';
import { reschedule } from '@/routes/panel/appointments';
import type {
    AgendaAppointment,
    AgendaService,
    AgendaStaff,
    SelectedAppointment,
} from '@/types';

const props = defineProps<{
    timezone: string;
    locale: string;
    staff: AgendaStaff[];
    services: AgendaService[];
    can: { viewAll: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Agenda', href: agenda() }],
    },
});

const calendar = useTemplateRef<InstanceType<typeof FullCalendar>>('calendar');

// Owners and admins may narrow the agenda to one professional; staff only
// ever have their own.
const staffFilter = ref<number | null>(
    props.can.viewAll ? null : (props.staff[0]?.id ?? null),
);
const showCancelled = ref(false);

/**
 * Wall-clock time of the business for an instant: "YYYY-MM-DDTHH:mm".
 * The agenda always shows the business timezone, whatever the browser's.
 */
const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: props.timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

const toLocal = (date: Date): string => {
    const parts = Object.fromEntries(
        formatter.formatToParts(date).map((part) => [part.type, part.value]),
    );

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};

const fetchEvents = async (
    info: EventSourceFuncInfo,
): Promise<EventInput[]> => {
    const query: Record<string, string> = {
        start: info.start.toISOString(),
        end: info.end.toISOString(),
    };

    if (staffFilter.value !== null) {
        query.staff_profile_id = String(staffFilter.value);
    }

    if (showCancelled.value) {
        query.include_cancelled = '1';
    }

    const response = await fetch(eventsRoute.url({ query }), {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        toast.error('The agenda could not be loaded. Please try again.');

        throw new Error(`Agenda feed answered ${response.status}`);
    }

    return response.json();
};

const refetch = () => calendar.value?.getApi().refetchEvents();

watch([staffFilter, showCancelled], refetch);

// Shade the time outside working hours of the professional(s) on screen.
const businessHours = computed(() => {
    const visible =
        staffFilter.value === null
            ? props.staff
            : props.staff.filter((member) => member.id === staffFilter.value);

    return visible.flatMap((member) => member.business_hours);
});

/* New appointment ---------------------------------------------------- */

const newOpen = ref(false);
const newStartsAt = ref('');

const roundedNow = (): string => {
    const now = new Date();
    now.setMinutes(Math.ceil(now.getMinutes() / 15) * 15, 0, 0);

    return toLocal(now);
};

const openNew = (startsAt: string) => {
    newStartsAt.value = startsAt;
    newOpen.value = true;
};

const onSelect = (info: DateSelectInfo) => {
    info.view.calendar.unselect();
    openNew(toLocal(info.start));
};

/* Existing appointments ------------------------------------------------ */

const sheetOpen = ref(false);
const selected = ref<SelectedAppointment | null>(null);

const onEventClick = (info: EventClickInfo) => {
    const props = info.event.extendedProps as AgendaAppointment;

    if (props.kind !== 'appointment' || info.event.start === null) {
        return;
    }

    selected.value = {
        ...props,
        id: info.event.id,
        local_start: toLocal(info.event.start),
        local_service_end: toLocal(new Date(props.service_ends_at)),
    };
    sheetOpen.value = true;
};

const onEventDrop = (info: EventDropInfo) => {
    if (info.event.start === null) {
        info.revert();

        return;
    }

    router.put(
        reschedule(info.event.id),
        { starts_at: toLocal(info.event.start) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: refetch,
            onError: (errors) => {
                info.revert();
                toast.error(
                    errors.starts_at ??
                        errors.appointment ??
                        'The appointment could not be moved.',
                );
            },
        },
    );
};

const options = computed<CalendarOptions>(() => ({
    plugins: [
        classicThemePlugin,
        timeGridPlugin,
        listPlugin,
        interactionPlugin,
    ],
    initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
    headerToolbar: {
        start: 'prev,next today',
        center: 'title',
        end: 'timeGridDay,timeGridWeek,listWeek',
    },
    buttonText: { today: 'Today', day: 'Day', week: 'Week', list: 'List' },
    timeZone: props.timezone,
    firstDay: 1,
    height: '100%',
    allDaySlot: false,
    nowIndicator: true,
    slotDuration: '00:15:00',
    slotHeaderInterval: '01:00',
    scrollTime: '08:00:00',
    slotHeaderFormat: { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' },
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' },
    businessHours: businessHours.value,
    selectable: true,
    selectMirror: true,
    editable: true,
    eventDurationEditable: false,
    events: fetchEvents,
    select: onSelect,
    eventClick: onEventClick,
    eventDrop: onEventDrop,
}));
</script>

<template>
    <Head title="Agenda" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <h1 class="mr-auto text-xl font-semibold tracking-tight">Agenda</h1>

            <div v-if="can.viewAll" class="flex items-center gap-2">
                <Label for="agenda-staff" class="sr-only">Professional</Label>
                <NativeSelect
                    id="agenda-staff"
                    v-model="staffFilter"
                    class="w-48"
                    data-test="agenda-staff-filter"
                >
                    <option :value="null">All professionals</option>
                    <option
                        v-for="member in staff"
                        :key="member.id"
                        :value="member.id"
                    >
                        {{ member.name }}
                    </option>
                </NativeSelect>
            </div>

            <Label class="flex items-center gap-2 font-normal">
                <Checkbox v-model="showCancelled" />
                Show cancelled
            </Label>

            <Button
                :disabled="staff.length === 0"
                data-test="new-appointment"
                @click="openNew(roundedNow())"
            >
                <Plus />
                New appointment
            </Button>
        </div>

        <ul
            v-if="can.viewAll && staff.length > 1"
            class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
        >
            <li
                v-for="member in staff"
                :key="member.id"
                class="flex items-center gap-1.5"
            >
                <span
                    class="size-2.5 rounded-full"
                    :style="{ backgroundColor: member.color }"
                />
                {{ member.name }}
            </li>
        </ul>

        <p
            v-if="staff.length === 0"
            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
        >
            Nobody takes bookings yet. Turn on "Takes bookings" for a team
            member to start filling the agenda.
        </p>

        <div class="agenda min-h-[560px] flex-1">
            <FullCalendar ref="calendar" :options="options" />
        </div>
    </div>

    <NewAppointmentDialog
        v-model:open="newOpen"
        :staff="staff"
        :services="services"
        :starts-at="newStartsAt"
        :staff-profile-id="staffFilter"
        @booked="refetch"
    />

    <AppointmentSheet
        v-model:open="sheetOpen"
        :appointment="selected"
        :staff="staff"
        :can-choose-staff="can.viewAll && staff.length > 1"
        @changed="refetch"
    />
</template>

<style>
/* FullCalendar's classic theme, painted with the app's design tokens so it
   follows light and dark mode. */
.agenda {
    --fc-classic-button: var(--secondary);
    --fc-classic-button-border: var(--border);
    /* The active view: one shade darker, same text colour (the theme has a
       single button foreground). */
    --fc-classic-button-strong: color-mix(
        in oklch,
        var(--foreground) 14%,
        var(--secondary)
    );
    --fc-classic-button-strong-border: var(--input);
    --fc-classic-button-outline: var(--ring);
    --fc-classic-button-foreground: var(--secondary-foreground);
    --fc-classic-primary: var(--primary);
    --fc-classic-primary-foreground: var(--primary-foreground);
    --fc-classic-event: var(--primary);
    /* Events take the professional's colour, a saturated mid-tone in both
       modes (StaffProfile::COLORS), so their text stays white. */
    --fc-classic-event-contrast: #fff;
    --fc-classic-background-event: var(--muted-foreground);
    --fc-classic-background-event-opacity: 18%;
    --fc-classic-background-event-foreground-opacity: 70%;
    --fc-classic-highlight: color-mix(in oklch, var(--ring) 25%, transparent);
    --fc-classic-today: color-mix(in oklch, var(--accent) 60%, transparent);
    --fc-classic-now: var(--destructive);
    --fc-classic-small-dot-width: 8px;
    --fc-classic-large-dot-width: 10px;
    --fc-classic-background: var(--background);
    --fc-classic-faint: color-mix(in oklch, var(--foreground) 4%, transparent);
    --fc-classic-muted: color-mix(in oklch, var(--foreground) 8%, transparent);
    --fc-classic-strong: color-mix(
        in oklch,
        var(--foreground) 14%,
        transparent
    );
    --fc-classic-foreground: var(--foreground);
    --fc-classic-faint-foreground: var(--muted-foreground);
    --fc-classic-muted-foreground: var(--muted-foreground);
    --fc-classic-border: var(--border);
    --fc-classic-strong-border: var(--input);

    font-size: 0.875rem;
}

.agenda .appointment-pending {
    background-image: repeating-linear-gradient(
        135deg,
        transparent 0 6px,
        rgb(255 255 255 / 0.25) 6px 12px
    );
}

.agenda .appointment-cancelled,
.agenda .appointment-no_show {
    opacity: 0.45;
    text-decoration: line-through;
}

.agenda .appointment-completed {
    opacity: 0.7;
}
</style>
