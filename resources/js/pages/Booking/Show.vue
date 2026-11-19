<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Clock, UserRound, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BookingShell from '@/components/booking/BookingShell.vue';
import MonthCalendar from '@/components/booking/MonthCalendar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { maskBrazilianPhone } from '@/composables/usePhoneMask';
import { store } from '@/routes/booking';
import {
    days as daysRoute,
    slots as slotsRoute,
} from '@/routes/booking/availability';
import type {
    PublicBusiness,
    PublicService,
    PublicSlot,
    PublicStaff,
} from '@/types';

const props = defineProps<{
    business: PublicBusiness;
    services: PublicService[];
    staff: PublicStaff[];
    today: string;
    maxDate: string;
}>();

type Step = 'service' | 'staff' | 'time' | 'details';

const step = ref<Step>('service');
const service = ref<PublicService | null>(null);
const staffChoice = ref<number | 'any'>('any');
const month = ref(props.today.slice(0, 7));
const availableDays = ref<string[]>([]);
const date = ref<string | null>(null);
const slots = ref<PublicSlot[]>([]);
const slot = ref<PublicSlot | null>(null);
const loadingDays = ref(false);
const loadingSlots = ref(false);
const loadError = ref<string | null>(null);

const eligibleStaff = computed(() =>
    service.value
        ? props.staff.filter((member) =>
              service.value!.staff_profile_ids.includes(member.id),
          )
        : [],
);

const staffName = computed(() =>
    staffChoice.value === 'any'
        ? 'Any professional'
        : props.staff.find((member) => member.id === staffChoice.value)?.name,
);

const query = (extra: Record<string, string>) => ({
    service_id: String(service.value!.id),
    ...(staffChoice.value === 'any'
        ? {}
        : { staff_profile_id: String(staffChoice.value) }),
    ...extra,
});

const getJson = async <T,>(url: string): Promise<T> => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return (await response.json()) as T;
};

const loadDays = async () => {
    loadingDays.value = true;
    loadError.value = null;

    try {
        const data = await getJson<{ days: string[] }>(
            daysRoute.url({ query: query({ month: month.value }) }),
        );
        availableDays.value = data.days;
    } catch {
        loadError.value = 'Could not load availability. Please try again.';
    } finally {
        loadingDays.value = false;
    }
};

const loadSlots = async () => {
    if (!date.value) {
        return;
    }

    loadingSlots.value = true;
    slot.value = null;

    try {
        const data = await getJson<{ slots: PublicSlot[] }>(
            slotsRoute.url({ query: query({ date: date.value }) }),
        );
        slots.value = data.slots;
    } catch {
        loadError.value = 'Could not load times. Please try again.';
    } finally {
        loadingSlots.value = false;
    }
};

watch(month, () => {
    if (step.value === 'time') {
        void loadDays();
    }
});

const chooseService = (chosen: PublicService) => {
    service.value = chosen;
    staffChoice.value = 'any';

    if (eligibleStaff.value.length === 1) {
        chooseStaff(eligibleStaff.value[0].id);
    } else {
        step.value = 'staff';
    }
};

const chooseStaff = (choice: number | 'any') => {
    staffChoice.value = choice;
    date.value = null;
    slots.value = [];
    slot.value = null;
    month.value = props.today.slice(0, 7);
    step.value = 'time';
    void loadDays();
};

const chooseDate = (chosen: string) => {
    date.value = chosen;
    void loadSlots();
};

const chooseSlot = (chosen: PublicSlot) => {
    slot.value = chosen;
    step.value = 'details';
};

const back = () => {
    if (step.value === 'details') {
        step.value = 'time';
    } else if (step.value === 'time') {
        step.value = eligibleStaff.value.length === 1 ? 'service' : 'staff';
    } else {
        step.value = 'service';
    }
};

const form = useForm({
    service_id: null as number | null,
    staff_profile_id: null as number | null,
    starts_at: null as string | null,
    name: '',
    email: '',
    phone: '',
    notes: '',
    // Honeypot: hidden from people, irresistible to bots.
    website: '',
});

const onPhoneInput = (event: Event) => {
    form.phone = maskBrazilianPhone((event.target as HTMLInputElement).value);
};

const submit = () => {
    form.service_id = service.value?.id ?? null;
    form.staff_profile_id =
        staffChoice.value === 'any' ? null : staffChoice.value;
    form.starts_at = slot.value?.starts_at ?? null;

    form.post(store.url(), {
        onError: (errors) => {
            if (errors.starts_at) {
                // Someone else took the time: pick another one.
                step.value = 'time';
                void loadDays();
                void loadSlots();
            }
        },
    });
};

const slotTakenError = computed(() => form.errors.starts_at);
const durationLabel = (minutes: number) =>
    minutes >= 60 && minutes % 60 === 0
        ? `${minutes / 60} h`
        : minutes > 60
          ? `${Math.floor(minutes / 60)} h ${minutes % 60} min`
          : `${minutes} min`;

const formatDate = (value: string) =>
    new Date(`${value}T12:00:00Z`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    });
</script>

<template>
    <Head :title="`Book with ${business.name}`" />

    <BookingShell :business="business">
        <div class="flex flex-col gap-6">
            <div class="flex items-center gap-2">
                <Button
                    v-if="step !== 'service'"
                    variant="ghost"
                    size="icon"
                    aria-label="Back"
                    data-test="booking-back"
                    @click="back"
                >
                    <ArrowLeft />
                </Button>
                <h1 class="text-xl font-semibold tracking-tight">
                    <template v-if="step === 'service'"
                        >Choose a service</template
                    >
                    <template v-else-if="step === 'staff'"
                        >Choose a professional</template
                    >
                    <template v-else-if="step === 'time'"
                        >Pick a day and time</template
                    >
                    <template v-else>Your details</template>
                </h1>
            </div>

            <!-- 1. Service -->
            <template v-if="step === 'service'">
                <p
                    v-if="!services.length"
                    class="rounded-lg border bg-background p-6 text-center text-sm text-muted-foreground"
                >
                    Online booking is not available at the moment.
                </p>
                <ul class="flex flex-col gap-3">
                    <li v-for="item in services" :key="item.id">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-4 rounded-lg border bg-background p-4 text-left transition-colors hover:border-primary"
                            :data-test="`service-${item.id}`"
                            @click="chooseService(item)"
                        >
                            <div class="min-w-0 space-y-1">
                                <p class="font-medium">{{ item.name }}</p>
                                <p
                                    v-if="item.description"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ item.description }}
                                </p>
                                <p
                                    class="flex items-center gap-1 text-sm text-muted-foreground"
                                >
                                    <Clock class="size-3.5" />
                                    {{ durationLabel(item.duration_minutes) }}
                                </p>
                            </div>
                            <span class="shrink-0 font-medium">
                                {{ item.price_formatted }}
                            </span>
                        </button>
                    </li>
                </ul>
            </template>

            <!-- 2. Professional -->
            <ul v-else-if="step === 'staff'" class="flex flex-col gap-3">
                <li>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg border bg-background p-4 text-left transition-colors hover:border-primary"
                        data-test="staff-any"
                        @click="chooseStaff('any')"
                    >
                        <Users class="size-5 text-muted-foreground" />
                        <div>
                            <p class="font-medium">Any professional</p>
                            <p class="text-sm text-muted-foreground">
                                See every available time
                            </p>
                        </div>
                    </button>
                </li>
                <li v-for="member in eligibleStaff" :key="member.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg border bg-background p-4 text-left transition-colors hover:border-primary"
                        :data-test="`staff-${member.id}`"
                        @click="chooseStaff(member.id)"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-medium text-white"
                            :style="{ backgroundColor: member.color }"
                        >
                            {{ member.name.charAt(0) }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium">{{ member.name }}</p>
                            <p
                                v-if="member.bio"
                                class="truncate text-sm text-muted-foreground"
                            >
                                {{ member.bio }}
                            </p>
                        </div>
                    </button>
                </li>
            </ul>

            <!-- 3. Day and time -->
            <div v-else-if="step === 'time'" class="flex flex-col gap-4">
                <p class="text-sm text-muted-foreground">
                    {{ service?.name }} · {{ staffName }}
                </p>

                <p
                    v-if="slotTakenError"
                    class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
                >
                    {{ slotTakenError }}
                </p>
                <p v-if="loadError" class="text-sm text-destructive">
                    {{ loadError }}
                </p>

                <MonthCalendar
                    v-model:month="month"
                    :available-days="availableDays"
                    :selected="date"
                    :min-month="today.slice(0, 7)"
                    :max-month="maxDate.slice(0, 7)"
                    :loading="loadingDays"
                    @select="chooseDate"
                />

                <p
                    v-if="!loadingDays && !availableDays.length"
                    class="text-sm text-muted-foreground"
                >
                    No free times this month. Try the next one.
                </p>

                <section v-if="date" class="flex flex-col gap-3">
                    <h2 class="text-sm font-medium capitalize">
                        {{ formatDate(date) }}
                    </h2>
                    <div v-if="loadingSlots" class="flex justify-center p-4">
                        <Spinner />
                    </div>
                    <p
                        v-else-if="!slots.length"
                        class="text-sm text-muted-foreground"
                    >
                        No free times left on this day.
                    </p>
                    <div v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <Button
                            v-for="item in slots"
                            :key="item.starts_at"
                            variant="outline"
                            :data-test="`slot-${item.label}`"
                            @click="chooseSlot(item)"
                        >
                            {{ item.label }}
                        </Button>
                    </div>
                </section>
            </div>

            <!-- 4. Details -->
            <form v-else class="flex flex-col gap-5" @submit.prevent="submit">
                <div
                    class="flex flex-col gap-1 rounded-lg border bg-background p-4 text-sm"
                >
                    <p class="font-medium">{{ service?.name }}</p>
                    <p class="text-muted-foreground capitalize">
                        {{ date ? formatDate(date) : '' }} · {{ slot?.label }}
                    </p>
                    <p class="flex items-center gap-1 text-muted-foreground">
                        <UserRound class="size-3.5" /> {{ staffName }}
                    </p>
                    <p class="font-medium">{{ service?.price_formatted }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="booking-name">Name</Label>
                    <Input
                        id="booking-name"
                        v-model="form.name"
                        autocomplete="name"
                        required
                        maxlength="255"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="booking-email">Email</Label>
                    <Input
                        id="booking-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        maxlength="255"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="booking-phone">Phone</Label>
                    <Input
                        id="booking-phone"
                        :model-value="form.phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        placeholder="(11) 98765-4321"
                        required
                        @input="onPhoneInput"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="booking-notes">Notes (optional)</Label>
                    <Textarea
                        id="booking-notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="1000"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="hidden" aria-hidden="true">
                    <label for="booking-website">Website</label>
                    <input
                        id="booking-website"
                        v-model="form.website"
                        tabindex="-1"
                        autocomplete="off"
                    />
                </div>
                <InputError
                    :message="
                        form.errors.website ??
                        form.errors.service_id ??
                        form.errors.staff_profile_id
                    "
                />

                <Button
                    type="submit"
                    size="lg"
                    :disabled="form.processing"
                    data-test="confirm-booking-button"
                >
                    <Spinner v-if="form.processing" />
                    Confirm booking
                </Button>
            </form>
        </div>
    </BookingShell>
</template>
