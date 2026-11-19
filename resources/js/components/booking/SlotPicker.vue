<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import MonthCalendar from '@/components/booking/MonthCalendar.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { PublicSlot } from '@/types';

const props = defineProps<{
    /** URL listing the days with free slots of a "YYYY-MM" month. */
    daysUrl: (month: string) => string;
    /** URL listing the free slots of a "YYYY-MM-DD" day. */
    slotsUrl: (date: string) => string;
    today: string;
    maxDate: string;
}>();

const emit = defineEmits<{
    pick: [slot: PublicSlot, date: string];
}>();

const month = ref(props.today.slice(0, 7));
const availableDays = ref<string[]>([]);
const date = ref<string | null>(null);
const slots = ref<PublicSlot[]>([]);
const loadingDays = ref(false);
const loadingSlots = ref(false);
const error = ref<string | null>(null);

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
    error.value = null;

    try {
        availableDays.value = (
            await getJson<{ days: string[] }>(props.daysUrl(month.value))
        ).days;
    } catch {
        error.value = 'Could not load availability. Please try again.';
    } finally {
        loadingDays.value = false;
    }
};

const loadSlots = async () => {
    if (!date.value) {
        return;
    }

    loadingSlots.value = true;

    try {
        slots.value = (
            await getJson<{ slots: PublicSlot[] }>(props.slotsUrl(date.value))
        ).slots;
    } catch {
        error.value = 'Could not load times. Please try again.';
    } finally {
        loadingSlots.value = false;
    }
};

const chooseDate = (chosen: string) => {
    date.value = chosen;
    void loadSlots();
};

const refresh = () => {
    void loadDays();
    void loadSlots();
};

watch(month, () => void loadDays());
onMounted(() => void loadDays());

defineExpose({ refresh });

const formatDate = (value: string) =>
    new Date(`${value}T12:00:00Z`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    });
</script>

<template>
    <div class="flex flex-col gap-4">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

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
            <p v-else-if="!slots.length" class="text-sm text-muted-foreground">
                No free times left on this day.
            </p>
            <div v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <Button
                    v-for="item in slots"
                    :key="item.starts_at"
                    variant="outline"
                    :data-test="`slot-${item.label}`"
                    @click="emit('pick', item, date)"
                >
                    {{ item.label }}
                </Button>
            </div>
        </section>
    </div>
</template>
