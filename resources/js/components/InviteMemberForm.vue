<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/team/invitations';
import type { RoleOption } from '@/types';

defineProps<{
    roles: RoleOption[];
}>();
</script>

<template>
    <Form
        v-bind="store.form()"
        :options="{ preserveScroll: true }"
        reset-on-success
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4 sm:flex-row sm:items-start"
    >
        <div class="grid flex-1 gap-2">
            <Label for="invite-email">Email address</Label>
            <Input
                id="invite-email"
                type="email"
                name="email"
                required
                autocomplete="off"
                placeholder="colleague@example.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2 sm:w-40">
            <Label for="invite-role">Role</Label>
            <select
                id="invite-role"
                name="role"
                required
                class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
            >
                <option
                    v-for="role in roles"
                    :key="role.value"
                    :value="role.value"
                    :selected="role.value === 'staff'"
                >
                    {{ role.label }}
                </option>
            </select>
            <InputError :message="errors.role" />
        </div>

        <Button
            type="submit"
            class="sm:mt-[1.375rem]"
            :disabled="processing"
            data-test="invite-member-button"
        >
            <Spinner v-if="processing" />
            Send invitation
        </Button>
    </Form>
</template>
