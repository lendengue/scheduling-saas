<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarClock, Check, Scissors, Users } from '@lucide/vue';
import InviteMemberForm from '@/components/InviteMemberForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, onboarding } from '@/routes';
import type { RoleOption } from '@/types';

defineProps<{
    canInvite: boolean;
    roles: RoleOption[];
    invitations: { id: number; email: string; role: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Get started', href: onboarding() }],
    },
});

const tenantName = usePage().props.tenant?.name;
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
            <li class="flex gap-4 rounded-lg border p-4 opacity-70">
                <Scissors class="mt-0.5 size-5 shrink-0" />
                <div class="flex-1 space-y-1">
                    <div class="flex items-center gap-2">
                        <h2 class="font-medium">1. Add your services</h2>
                        <Badge variant="outline">Coming soon</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Name, duration and price of what you offer.
                    </p>
                </div>
            </li>

            <li class="flex gap-4 rounded-lg border p-4 opacity-70">
                <CalendarClock class="mt-0.5 size-5 shrink-0" />
                <div class="flex-1 space-y-1">
                    <div class="flex items-center gap-2">
                        <h2 class="font-medium">2. Set your working hours</h2>
                        <Badge variant="outline">Coming soon</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        When each professional is available for bookings.
                    </p>
                </div>
            </li>

            <li class="flex gap-4 rounded-lg border p-4">
                <Users class="mt-0.5 size-5 shrink-0" />
                <div class="flex flex-1 flex-col gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h2 class="font-medium">3. Invite your team</h2>
                            <Badge variant="secondary">Optional</Badge>
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

        <div class="flex justify-end">
            <Button as-child>
                <Link :href="dashboard()" data-test="finish-onboarding-button"
                    >Go to dashboard</Link
                >
            </Button>
        </div>
    </div>
</template>
