<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    Check,
    Circle,
    ExternalLink,
    Plus,
    Scissors,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InviteMemberForm from '@/components/InviteMemberForm.vue';
import ServiceFormDialog from '@/components/ServiceFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import WorkingHoursEditor from '@/components/WorkingHoursEditor.vue';
import { agenda, onboarding } from '@/routes';
import type { RoleOption, StaffOption, WorkingInterval } from '@/types';

const props = defineProps<{
    canManageServices: boolean;
    services: {
        id: number;
        name: string;
        duration_minutes: number;
        price_formatted: string;
    }[];
    staff: StaffOption[];
    hours: WorkingInterval[];
    canInvite: boolean;
    roles: RoleOption[];
    invitations: { id: number; email: string; role: string }[];
    bookingUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Get started', href: onboarding() }],
    },
});

const page = usePage();
const tenantName = page.props.tenant?.name;
const userId = page.props.auth.user.id;

const serviceDialogOpen = ref(false);

// Customers can book once there is something to book and time to book it.
const ready = computed(
    () => props.services.length > 0 && props.hours.length > 0,
);
</script>

<template>
    <Head title="Get started" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 md:p-8">
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold tracking-tight">
                Welcome to scheduling-saas, {{ tenantName }}!
            </h1>
            <p class="text-muted-foreground">
                Three steps and your booking page is ready for customers.
            </p>
        </header>

        <ol class="flex flex-col gap-4">
            <li
                v-if="canManageServices"
                class="flex gap-4 rounded-lg border p-4"
                data-test="onboarding-services"
            >
                <Scissors class="mt-0.5 size-5 shrink-0" />
                <div class="flex flex-1 flex-col gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h2 class="font-medium">1. Add your services</h2>
                            <Badge v-if="services.length" variant="secondary">
                                <Check /> Done
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Name, duration and price of what you offer.
                            Customers pick one of them on your booking page.
                        </p>
                    </div>

                    <ul v-if="services.length" class="space-y-1 text-sm">
                        <li
                            v-for="service in services"
                            :key="service.id"
                            class="flex items-center gap-2"
                        >
                            <Check class="size-4 text-green-600" />
                            {{ service.name }}
                            <span class="text-muted-foreground">
                                · {{ service.duration_minutes }} min ·
                                {{ service.price_formatted }}
                            </span>
                        </li>
                    </ul>

                    <div>
                        <Button
                            :variant="services.length ? 'outline' : 'default'"
                            data-test="onboarding-add-service"
                            @click="serviceDialogOpen = true"
                        >
                            <Plus />
                            {{
                                services.length
                                    ? 'Add another service'
                                    : 'Add a service'
                            }}
                        </Button>
                    </div>
                </div>
            </li>

            <li
                class="flex gap-4 rounded-lg border p-4"
                data-test="onboarding-hours"
            >
                <CalendarClock class="mt-0.5 size-5 shrink-0" />
                <div class="flex flex-1 flex-col gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h2 class="font-medium">
                                2. Set your working hours
                            </h2>
                            <Badge v-if="hours.length" variant="secondary">
                                <Check /> Done
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            When you are available for bookings. Each team
                            member's hours are set on the Team page.
                        </p>
                    </div>

                    <WorkingHoursEditor :member-id="userId" :hours="hours" />
                </div>
            </li>

            <li class="flex gap-4 rounded-lg border p-4">
                <Users class="mt-0.5 size-5 shrink-0" />
                <div class="flex flex-1 flex-col gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h2 class="font-medium">3. Invite your team</h2>
                            <Badge variant="outline">Optional</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Admins help you manage the business; staff manage
                            their own schedule.
                        </p>
                    </div>

                    <InviteMemberForm v-if="canInvite" :roles="roles" />

                    <ul v-if="invitations.length" class="space-y-1 text-sm">
                        <li
                            v-for="invitation in invitations"
                            :key="invitation.id"
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <Check class="size-4 text-green-600" />
                            {{ invitation.email }} ({{ invitation.role }})
                        </li>
                    </ul>
                </div>
            </li>
        </ol>

        <div
            class="flex flex-col gap-4 rounded-lg bg-muted p-4 sm:flex-row sm:items-center"
        >
            <div class="flex flex-1 items-start gap-3 text-sm">
                <component
                    :is="ready ? Check : Circle"
                    :class="[
                        'mt-0.5 size-4 shrink-0',
                        ready && 'text-green-600',
                    ]"
                />
                <p v-if="ready">
                    Your booking page is live:
                    <a
                        :href="bookingUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 font-medium underline underline-offset-4"
                        data-test="onboarding-booking-url"
                        >{{ bookingUrl }} <ExternalLink class="size-3.5"
                    /></a>
                </p>
                <p v-else class="text-muted-foreground">
                    Customers can book once you have a service and working
                    hours.
                </p>
            </div>
            <Button as-child>
                <Link :href="agenda()" data-test="finish-onboarding-button"
                    >Go to my agenda</Link
                >
            </Button>
        </div>
    </div>

    <ServiceFormDialog
        v-model:open="serviceDialogOpen"
        :service="null"
        :staff="staff"
    />
</template>
