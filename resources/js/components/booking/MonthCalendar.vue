<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps<{
    /** "YYYY-MM" */
    month: string;
    /** "YYYY-MM-DD" dates with at least one free slot. */
    availableDays: string[];
    selected: string | null;
    minMonth: string;
    maxMonth: string;
    loading?: boolean;
}>();

const emit = defineEmits<{
    select: [date: string];
    'update:month': [month: string];
}>();

const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const pad = (value: number) => String(value).padStart(2, '0');

const shiftMonth = (month: string, delta: number): string => {
    const [year, monthIndex] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, monthIndex - 1 + delta, 1));

    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}`;
};

const title = computed(() => {
    const [year, monthIndex] = props.month.split('-').map(Number);

    return new Date(Date.UTC(year, monthIndex - 1, 1)).toLocaleDateString(
        undefined,
        { month: 'long', year: 'numeric', timeZone: 'UTC' },
    );
});

// Calendar dates are plain "YYYY-MM-DD" strings (the business's days), so
// all arithmetic happens in UTC to stay independent of the visitor's zone.
const cells = computed(() => {
    const [year, monthIndex] = props.month.split('-').map(Number);
    const first = new Date(Date.UTC(year, monthIndex - 1, 1));
    const daysInMonth = new Date(Date.UTC(year, monthIndex, 0)).getUTCDate();
    const leading = (first.getUTCDay() + 6) % 7;
    const available = new Set(props.availableDays);

    return [
        ...Array.from({ length: leading }, () => null),
        ...Array.from({ length: daysInMonth }, (_, i) => {
            const date = `${props.month}-${pad(i + 1)}`;

            return { date, day: i + 1, available: available.has(date) };
        }),
    ];
});
</script>

<template>
    <div class="rounded-lg border bg-background p-3">
        <div class="mb-3 flex items-center justify-between">
            <Button
                variant="ghost"
                size="icon"
                :disabled="month <= minMonth"
                aria-label="Previous month"
                @click="emit('update:month', shiftMonth(month, -1))"
            >
                <ChevronLeft />
            </Button>
            <p class="text-sm font-medium capitalize">{{ title }}</p>
            <Button
                variant="ghost"
                size="icon"
                :disabled="month >= maxMonth"
                aria-label="Next month"
                @click="emit('update:month', shiftMonth(month, 1))"
            >
                <ChevronRight />
            </Button>
        </div>

        <div
            class="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground"
        >
            <span v-for="label in weekdayLabels" :key="label" class="py-1">
                {{ label }}
            </span>
        </div>

        <div
            class="grid grid-cols-7 gap-1"
            :class="{ 'animate-pulse opacity-60': loading }"
        >
            <template v-for="(cell, index) in cells" :key="index">
                <span v-if="cell === null" />
                <button
                    v-else
                    type="button"
                    :disabled="!cell.available || loading"
                    :aria-pressed="selected === cell.date"
                    :data-date="cell.date"
                    :class="
                        cn(
                            'aspect-square rounded-md text-sm transition-colors',
                            cell.available
                                ? 'font-medium hover:bg-primary/10'
                                : 'cursor-not-allowed text-muted-foreground/50 line-through decoration-transparent',
                            selected === cell.date &&
                                'bg-primary text-primary-foreground hover:bg-primary',
                        )
                    "
                    @click="emit('select', cell.date)"
                >
                    {{ cell.day }}
                </button>
            </template>
        </div>
    </div>
</template>
