<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarPlus, CircleCheck, Hourglass } from '@lucide/vue';
import BookingShell from '@/components/booking/BookingShell.vue';
import { Button } from '@/components/ui/button';
import type { AppointmentSummary, PublicBusiness } from '@/types';

defineProps<{
    business: PublicBusiness;
    appointment: AppointmentSummary;
    isPending: boolean;
    calendarUrl: string;
}>();
</script>

<template>
    <Head title="Booking confirmed" />

    <BookingShell :business="business">
        <div class="flex flex-col items-center gap-6 text-center">
            <component
                :is="isPending ? Hourglass : CircleCheck"
                class="size-12 text-primary"
            />
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ isPending ? 'Request received' : 'You are booked!' }}
                </h1>
                <p class="text-muted-foreground">
                    <template v-if="isPending">
                        {{ business.name }} will confirm your appointment
                        shortly.
                    </template>
                    <template v-else>
                        We sent the details to your email.
                    </template>
                </p>
            </div>

            <dl
                class="grid w-full gap-3 rounded-lg border bg-background p-5 text-left text-sm sm:grid-cols-2"
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

            <div
                class="flex w-full flex-col gap-3 sm:flex-row sm:justify-center"
            >
                <Button as-child variant="outline">
                    <a :href="calendarUrl" data-test="add-to-calendar">
                        <CalendarPlus /> Add to calendar
                    </a>
                </Button>
            </div>
        </div>
    </BookingShell>
</template>
