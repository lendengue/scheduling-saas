<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    GripVertical,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import ServiceFormDialog from '@/components/ServiceFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { destroy, index, reorder } from '@/routes/services';
import type { ServiceItem, StaffOption } from '@/types';

const props = defineProps<{
    services: ServiceItem[];
    staff: StaffOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Services', href: index() }],
    },
});

const items = ref<ServiceItem[]>([...props.services]);
watch(
    () => props.services,
    (services) => (items.value = [...services]),
);

const dialogOpen = ref(false);
const editing = ref<ServiceItem | null>(null);

const openCreate = () => {
    editing.value = null;
    dialogOpen.value = true;
};

const openEdit = (service: ServiceItem) => {
    editing.value = service;
    dialogOpen.value = true;
};

const remove = (service: ServiceItem) => {
    router.delete(destroy(service.id), { preserveScroll: true });
};

const saveOrder = () => {
    router.put(
        reorder(),
        { ids: items.value.map((service) => service.id) },
        { preserveScroll: true },
    );
};

const move = (from: number, to: number) => {
    if (to < 0 || to >= items.value.length || from === to) {
        return;
    }

    const next = [...items.value];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);
    items.value = next;
};

// Native drag and drop; the arrow buttons cover keyboard and touch users.
const dragging = ref<number | null>(null);

const onDragStart = (position: number) => (dragging.value = position);

const onDragEnter = (position: number) => {
    if (dragging.value === null || dragging.value === position) {
        return;
    }

    move(dragging.value, position);
    dragging.value = position;
};

const onDragEnd = () => {
    dragging.value = null;
    saveOrder();
};

const moveAndSave = (from: number, to: number) => {
    move(from, to);
    saveOrder();
};

const durationLabel = (service: ServiceItem): string =>
    service.buffer_after_minutes > 0
        ? `${service.duration_minutes} min + ${service.buffer_after_minutes} min buffer`
        : `${service.duration_minutes} min`;
</script>

<template>
    <Head title="Services" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Services"
                description="What customers can book. Drag to change the order shown on your booking page."
            />
            <Button data-test="new-service-button" @click="openCreate">
                <Plus /> New service
            </Button>
        </div>

        <p
            v-if="!items.length"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            No services yet. Add the first one to open your booking page.
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="(service, position) in items"
                :key="service.id"
                draggable="true"
                class="flex items-center gap-3 bg-background px-3 py-3"
                :class="{ 'opacity-50': dragging === position }"
                @dragstart="onDragStart(position)"
                @dragenter.prevent="onDragEnter(position)"
                @dragover.prevent
                @dragend="onDragEnd"
            >
                <GripVertical
                    class="size-4 shrink-0 cursor-grab text-muted-foreground"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-medium">
                            {{ service.name }}
                        </p>
                        <Badge v-if="!service.is_active" variant="outline">
                            Hidden
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ durationLabel(service) }} ·
                        {{ service.price_formatted }}
                    </p>
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        :disabled="position === 0"
                        aria-label="Move up"
                        @click="moveAndSave(position, position - 1)"
                    >
                        <ArrowUp />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        :disabled="position === items.length - 1"
                        aria-label="Move down"
                        @click="moveAndSave(position, position + 1)"
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Edit"
                        @click="openEdit(service)"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Delete"
                        @click="remove(service)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </li>
        </ul>

        <ServiceFormDialog
            v-model:open="dialogOpen"
            :service="editing"
            :staff="staff"
        />
    </div>
</template>
