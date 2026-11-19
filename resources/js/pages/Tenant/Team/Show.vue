<script setup lang="ts">
import { Form, Head, Link, useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import WorkingHoursEditor from '@/components/WorkingHoursEditor.vue';
import { index as teamIndex } from '@/routes/team';
import { show, update } from '@/routes/team/members';
import { destroy, store } from '@/routes/team/members/time-offs';
import type { Role, RoleOption, TimeOffItem, WorkingInterval } from '@/types';

const props = defineProps<{
    member: {
        id: number;
        name: string;
        email: string;
        role: Role | null;
        is_owner: boolean;
        is_self: boolean;
    };
    profile: {
        bio: string | null;
        color: string;
        is_bookable: boolean;
        service_ids: number[];
    };
    hours: WorkingInterval[];
    timeOffs: TimeOffItem[];
    services: { id: number; name: string }[];
    roles: RoleOption[];
    timezone: string;
    can: { update: boolean };
}>();

defineOptions({
    layout: (props: { member: { id: number; name: string } }) => ({
        breadcrumbs: [
            { title: 'Team', href: teamIndex() },
            { title: props.member.name, href: show(props.member.id) },
        ],
    }),
});

const profileForm = useForm({
    role: props.member.is_owner ? null : props.member.role,
    is_bookable: props.profile.is_bookable,
    color: props.profile.color,
    bio: props.profile.bio ?? '',
    service_ids: [...props.profile.service_ids],
});

const saveProfile = () =>
    profileForm.put(update.url(props.member.id), { preserveScroll: true });

const toggleService = (id: number, checked: boolean) => {
    profileForm.service_ids = checked
        ? [...profileForm.service_ids, id]
        : profileForm.service_ids.filter((value) => value !== id);
};

const formatRange = (timeOff: TimeOffItem): string =>
    `${timeOff.starts_at} → ${timeOff.ends_at}`;
</script>

<template>
    <Head :title="member.name" />

    <div class="flex max-w-3xl flex-col gap-10 p-4 md:p-6">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ member.name }}
            </h1>
            <p class="text-sm text-muted-foreground">{{ member.email }}</p>
        </header>

        <section v-if="can.update" class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="Profile"
                description="Role, booking availability and the services this person performs."
            />
            <form class="flex flex-col gap-5" @submit.prevent="saveProfile">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="member-role">Role</Label>
                        <NativeSelect
                            id="member-role"
                            v-model="profileForm.role"
                            :disabled="member.is_owner"
                        >
                            <option v-if="member.is_owner" :value="null">
                                Owner
                            </option>
                            <option
                                v-for="role in roles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </option>
                        </NativeSelect>
                        <InputError :message="profileForm.errors.role" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="member-color">Agenda color</Label>
                        <Input
                            id="member-color"
                            v-model="profileForm.color"
                            type="color"
                            class="h-9 w-20 p-1"
                        />
                        <InputError :message="profileForm.errors.color" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="profileForm.is_bookable"
                        type="checkbox"
                        class="size-4 accent-primary"
                        data-test="is-bookable-checkbox"
                    />
                    Customers can book appointments with this person
                </label>

                <fieldset v-if="services.length" class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">
                        Services performed
                    </legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="service in services"
                            :key="service.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="size-4 accent-primary"
                                :checked="
                                    profileForm.service_ids.includes(service.id)
                                "
                                @change="
                                    toggleService(
                                        service.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            {{ service.name }}
                        </label>
                    </div>
                    <InputError :message="profileForm.errors.service_ids" />
                </fieldset>

                <div class="grid gap-2">
                    <Label for="member-bio">Short bio</Label>
                    <Textarea
                        id="member-bio"
                        v-model="profileForm.bio"
                        rows="2"
                        maxlength="1000"
                    />
                    <InputError :message="profileForm.errors.bio" />
                </div>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        :disabled="profileForm.processing"
                        data-test="save-profile-button"
                    >
                        <Spinner v-if="profileForm.processing" />
                        Save profile
                    </Button>
                </div>
            </form>
        </section>

        <section class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="Working hours"
                :description="`Weekly schedule in ${timezone} time. Add several intervals to a day for breaks.`"
            />
            <WorkingHoursEditor :member-id="member.id" :hours="hours" />
        </section>

        <section class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="Time off"
                description="Vacations, days off or blocked hours. Customers cannot book during them."
            />

            <Form
                v-bind="store.form(member.id)"
                :options="{ preserveScroll: true }"
                reset-on-success
                v-slot="{ errors, processing }"
                class="grid gap-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-start"
            >
                <div class="grid gap-2">
                    <Label for="time-off-start">From</Label>
                    <Input
                        id="time-off-start"
                        name="starts_at"
                        type="datetime-local"
                        required
                    />
                    <InputError :message="errors.starts_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="time-off-end">To</Label>
                    <Input
                        id="time-off-end"
                        name="ends_at"
                        type="datetime-local"
                        required
                    />
                    <InputError :message="errors.ends_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="time-off-reason">Reason (optional)</Label>
                    <Input id="time-off-reason" name="reason" maxlength="255" />
                    <InputError :message="errors.reason" />
                </div>
                <Button
                    type="submit"
                    class="sm:mt-[1.375rem]"
                    :disabled="processing"
                    data-test="add-time-off-button"
                >
                    <Spinner v-if="processing" />
                    Add
                </Button>
            </Form>

            <ul v-if="timeOffs.length" class="divide-y rounded-lg border">
                <li
                    v-for="timeOff in timeOffs"
                    :key="timeOff.id"
                    class="flex items-center justify-between gap-4 px-4 py-3"
                >
                    <div class="min-w-0 text-sm">
                        <p class="font-medium">{{ formatRange(timeOff) }}</p>
                        <p v-if="timeOff.reason" class="text-muted-foreground">
                            {{ timeOff.reason }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" as-child>
                        <Link
                            :href="destroy([member.id, timeOff.id])"
                            as="button"
                            preserve-scroll
                            aria-label="Remove time off"
                        >
                            <Trash2 />
                        </Link>
                    </Button>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No upcoming time off.
            </p>
        </section>
    </div>
</template>
