<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InviteMemberForm from '@/components/InviteMemberForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/team';
import { show } from '@/routes/team/members';
import { destroy } from '@/routes/team/invitations';
import type { RoleOption } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    is_bookable: boolean;
    color: string | null;
};

type PendingInvitation = {
    id: number;
    email: string;
    role: string;
    expires_at: string;
};

defineProps<{
    members: Member[];
    invitations: PendingInvitation[];
    roles: RoleOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Team', href: index() }],
    },
});

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <Head title="Team" />

    <div class="flex flex-col gap-10 p-4 md:p-6">
        <section class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="Invite a team member"
                description="They will receive an email with a link to join. Invitations expire after 7 days."
            />
            <InviteMemberForm :roles="roles" />
        </section>

        <section class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="Members"
                :description="`${members.length} ${members.length === 1 ? 'person' : 'people'} in this workspace`"
            />
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="member in members"
                    :key="member.id"
                    class="flex items-center justify-between gap-4 px-4 py-3"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="size-3 shrink-0 rounded-full"
                            :style="{
                                backgroundColor: member.color ?? 'transparent',
                            }"
                        />
                        <div class="min-w-0">
                            <Link
                                :href="show(member.id)"
                                class="block truncate text-sm font-medium hover:underline"
                            >
                                {{ member.name }}
                            </Link>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ member.email }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge v-if="member.is_bookable" variant="outline">
                            Takes bookings
                        </Badge>
                        <Badge v-if="member.role" variant="secondary">
                            {{ member.role }}
                        </Badge>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="invitations.length" class="flex flex-col gap-4">
            <Heading variant="small" title="Pending invitations" />
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="flex items-center justify-between gap-4 px-4 py-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ invitation.email }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ invitation.role }} · expires
                            {{ formatDate(invitation.expires_at) }}
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" as-child>
                        <Link
                            :href="destroy(invitation.id)"
                            as="button"
                            preserve-scroll
                            data-test="revoke-invitation-button"
                        >
                            Revoke
                        </Link>
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
