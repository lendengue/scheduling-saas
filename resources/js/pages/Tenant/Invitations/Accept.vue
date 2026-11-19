<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accept } from '@/routes/invitations';

defineProps<{
    invitation: { id: number; email: string; role: string };
    tenantName: string;
    token: string;
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Join your team',
        description: 'Choose your name and a password to accept the invitation',
    },
});
</script>

<template>
    <Head title="Accept invitation" />

    <p class="text-center text-sm text-muted-foreground">
        You were invited to <strong>{{ tenantName }}</strong> as
        <strong>{{ invitation.role }}</strong>.
    </p>

    <Form
        v-bind="accept.form(invitation.id)"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input type="hidden" name="token" :value="token" />
        <InputError :message="errors.token" />

        <div class="grid gap-2">
            <Label for="email">Email address</Label>
            <Input id="email" :model-value="invitation.email" disabled />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                name="name"
                required
                v-focus
                autocomplete="name"
                placeholder="Full name"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirm password</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Confirm password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="accept-invitation-button"
        >
            <Spinner v-if="processing" />
            Join {{ tenantName }}
        </Button>
    </Form>
</template>
