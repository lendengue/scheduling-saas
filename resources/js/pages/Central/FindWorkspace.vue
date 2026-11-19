<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { find } from '@/routes/workspace';

defineOptions({
    layout: {
        title: 'Log in to your workspace',
        description: 'Enter the address of your business on scheduling-saas',
    },
});

const appDomain = usePage().props.appDomain;
</script>

<template>
    <Head title="Log in" />

    <Form
        v-bind="find.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="slug">Workspace address</Label>
            <div
                class="flex items-center rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
            >
                <input
                    id="slug"
                    name="slug"
                    required
                    v-focus
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="your-business"
                    class="h-9 min-w-0 flex-1 bg-transparent px-3 py-1 text-base outline-none md:text-sm"
                />
                <span class="pr-3 text-sm text-muted-foreground"
                    >.{{ appDomain }}</span
                >
            </div>
            <InputError :message="errors.slug" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="find-workspace-button"
        >
            <Spinner v-if="processing" />
            Continue
        </Button>

        <div class="text-center text-sm text-muted-foreground">
            New to scheduling-saas?
            <TextLink :href="register()">Create your business</TextLink>
        </div>
    </Form>
</template>
