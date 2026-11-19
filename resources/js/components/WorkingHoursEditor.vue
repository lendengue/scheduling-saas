<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/team/members/working-hours';
import type { WorkingInterval } from '@/types';

const props = defineProps<{
    memberId: number;
    hours: WorkingInterval[];
}>();

// Monday first, as most calendars in our markets show it.
const weekdays = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 0, label: 'Sunday' },
];

const form = useForm({
    hours: props.hours.map((interval) => ({ ...interval })),
});

const byDay = computed(() =>
    weekdays.map((day) => ({
        ...day,
        intervals: form.hours
            .map((interval, index) => ({ interval, index }))
            .filter(({ interval }) => interval.weekday === day.value),
    })),
);

const addInterval = (weekday: number) => {
    const sameDay = form.hours.filter((i) => i.weekday === weekday);
    const last = sameDay[sameDay.length - 1];

    form.hours.push(
        last
            ? { weekday, starts_at: last.ends_at, ends_at: '18:00' }
            : { weekday, starts_at: '09:00', ends_at: '18:00' },
    );
};

const removeInterval = (index: number) => form.hours.splice(index, 1);

const copyMondayToWeekdays = () => {
    const monday = form.hours.filter((i) => i.weekday === 1);
    form.hours = [
        ...form.hours.filter((i) => ![1, 2, 3, 4, 5].includes(i.weekday)),
        ...[1, 2, 3, 4, 5].flatMap((weekday) =>
            monday.map((i) => ({ ...i, weekday })),
        ),
    ];
};

const submit = () =>
    form.put(update.url(props.memberId), { preserveScroll: true });

const firstError = computed(
    () =>
        form.errors.hours ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('hours.'),
        )?.[1],
);
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <ul class="divide-y rounded-lg border">
            <li
                v-for="day in byDay"
                :key="day.value"
                class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start"
                :data-test="`weekday-${day.value}`"
            >
                <span class="w-28 pt-2 text-sm font-medium">
                    {{ day.label }}
                </span>
                <div class="flex flex-1 flex-col gap-2">
                    <p
                        v-if="!day.intervals.length"
                        class="pt-2 text-sm text-muted-foreground"
                    >
                        Not working
                    </p>
                    <div
                        v-for="{ interval, index } in day.intervals"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <Input
                            v-model="interval.starts_at"
                            type="time"
                            step="300"
                            class="w-32"
                            required
                            aria-label="Starts at"
                        />
                        <span class="text-muted-foreground">–</span>
                        <Input
                            v-model="interval.ends_at"
                            type="time"
                            step="300"
                            class="w-32"
                            required
                            aria-label="Ends at"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove interval"
                            @click="removeInterval(index)"
                        >
                            <X />
                        </Button>
                    </div>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="addInterval(day.value)"
                >
                    <Plus /> Add
                </Button>
            </li>
        </ul>

        <InputError :message="firstError" />

        <div class="flex flex-wrap justify-end gap-2">
            <Button
                type="button"
                variant="outline"
                @click="copyMondayToWeekdays"
            >
                Copy Monday to weekdays
            </Button>
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="save-hours-button"
            >
                <Spinner v-if="form.processing" />
                Save hours
            </Button>
        </div>
    </form>
</template>
