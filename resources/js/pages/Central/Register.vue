<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    timezones: string[];
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create your business',
        description:
            'Get your own booking page and a 14-day free trial of the Pro plan',
    },
});

const appDomain = usePage().props.appDomain;

const businessName = ref('');
const slug = ref('');
const slugEdited = ref(false);

const slugify = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 30)
        .replace(/-+$/g, '');

watch(businessName, (name) => {
    if (!slugEdited.value) {
        slug.value = slugify(name);
    }
});

const onSlugInput = (): void => {
    slugEdited.value = true;
    slug.value = slug.value.toLowerCase();
};

const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const timezone = ref(
    props.timezones.includes(browserTimezone)
        ? browserTimezone
        : 'America/Sao_Paulo',
);
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="business_name">Business name</Label>
                <Input
                    id="business_name"
                    v-model="businessName"
                    name="business_name"
                    required
                    v-focus
                    autocomplete="organization"
                    placeholder="Acme Barbershop"
                />
                <InputError :message="errors.business_name" />
            </div>

            <div class="grid gap-2">
                <Label for="slug">Booking page address</Label>
                <div
                    class="flex items-center rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
                >
                    <input
                        id="slug"
                        v-model="slug"
                        name="slug"
                        required
                        minlength="3"
                        maxlength="30"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="acme"
                        class="h-9 min-w-0 flex-1 bg-transparent px-3 py-1 text-base outline-none md:text-sm"
                        @input="onSlugInput"
                    />
                    <span class="pr-3 text-sm text-muted-foreground"
                        >.{{ appDomain }}</span
                    >
                </div>
                <p class="text-xs text-muted-foreground">
                    Lowercase letters, numbers and hyphens. This cannot be
                    changed later.
                </p>
                <InputError :message="errors.slug" />
            </div>

            <div class="grid gap-2">
                <Label for="timezone">Time zone</Label>
                <select
                    id="timezone"
                    v-model="timezone"
                    name="timezone"
                    required
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                >
                    <option v-for="tz in timezones" :key="tz" :value="tz">
                        {{ tz }}
                    </option>
                </select>
                <InputError :message="errors.timezone" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Your name</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
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
                class="mt-2 w-full"
                :disabled="processing"
                data-test="register-tenant-button"
            >
                <Spinner v-if="processing" />
                Create my business
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have a workspace?
            <TextLink :href="login()" class="underline underline-offset-4"
                >Log in</TextLink
            >
        </div>
    </Form>
</template>
