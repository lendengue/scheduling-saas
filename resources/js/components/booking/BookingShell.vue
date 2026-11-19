<script setup lang="ts">
import { computed } from 'vue';
import type { PublicBusiness } from '@/types';

const props = defineProps<{
    business: PublicBusiness;
}>();

/**
 * Pick a readable text color for the brand color (WCAG relative luminance).
 */
const foregroundFor = (hex: string): string => {
    const channel = (value: number) => {
        const c = value / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const [r, g, b] = [1, 3, 5].map((i) =>
        channel(parseInt(hex.slice(i, i + 2), 16)),
    );

    return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.4 ? '#111111' : '#ffffff';
};

const brandStyle = computed(() =>
    props.business.brand_color
        ? {
              '--primary': props.business.brand_color,
              '--primary-foreground': foregroundFor(props.business.brand_color),
              '--ring': props.business.brand_color,
          }
        : {},
);
</script>

<template>
    <div class="min-h-svh bg-muted/40" :style="brandStyle">
        <header class="border-b bg-background">
            <div class="mx-auto flex max-w-2xl items-center gap-3 px-4 py-4">
                <img
                    v-if="business.logo_url"
                    :src="business.logo_url"
                    :alt="business.name"
                    class="size-10 rounded-md object-cover"
                />
                <div
                    v-else
                    class="flex size-10 items-center justify-center rounded-md bg-primary text-lg font-semibold text-primary-foreground"
                    aria-hidden="true"
                >
                    {{ business.name.charAt(0) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ business.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        Times shown in {{ business.timezone }}
                    </p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-6">
            <slot />
        </main>

        <footer class="pb-8 text-center text-xs text-muted-foreground">
            Powered by scheduling-saas
        </footer>
    </div>
</template>
