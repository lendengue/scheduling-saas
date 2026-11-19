<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BellRing, CalendarCheck, ShieldCheck } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

const features = [
    {
        icon: CalendarCheck,
        title: 'Your own booking page',
        description:
            'Customers pick a service, a professional and a free slot on your subdomain, no account needed.',
    },
    {
        icon: ShieldCheck,
        title: 'No double bookings',
        description:
            'Availability respects working hours, breaks and buffers, and conflicts are blocked by the database itself.',
    },
    {
        icon: BellRing,
        title: 'Fewer no-shows',
        description:
            'Automatic confirmations and reminders by email and WhatsApp keep your customers on time.',
    },
];
</script>

<template>
    <Head title="Online scheduling for small businesses" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-5xl items-center justify-between p-6"
        >
            <div class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-7 fill-current" />
                scheduling-saas
            </div>
            <nav class="flex items-center gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="login()">Log in</Link>
                </Button>
                <Button as-child>
                    <Link :href="register()">Get started</Link>
                </Button>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-16 px-6 py-12"
        >
            <section class="max-w-2xl space-y-6">
                <h1
                    class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    Stop scheduling over WhatsApp.
                </h1>
                <p class="text-lg text-muted-foreground">
                    scheduling-saas gives barbershops, clinics, studios and
                    personal trainers an online booking page, a shared team
                    calendar and automatic reminders.
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button size="lg" as-child>
                        <Link :href="register()"
                            >Start your 14-day free trial</Link
                        >
                    </Button>
                    <Button size="lg" variant="outline" as-child>
                        <Link :href="login()">Find your workspace</Link>
                    </Button>
                </div>
            </section>

            <section class="grid gap-6 sm:grid-cols-3">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="space-y-2 rounded-xl border p-5"
                >
                    <component :is="feature.icon" class="size-5" />
                    <h2 class="font-medium">{{ feature.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ feature.description }}
                    </p>
                </div>
            </section>
        </main>

        <footer
            class="mx-auto w-full max-w-5xl p-6 text-sm text-muted-foreground"
        >
            scheduling-saas · open source under the MIT license
        </footer>
    </div>
</template>
