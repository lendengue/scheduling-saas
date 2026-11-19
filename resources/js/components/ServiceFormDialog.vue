<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/services';
import type { ServiceItem, StaffOption } from '@/types';

const props = defineProps<{
    service: ServiceItem | null;
    staff: StaffOption[];
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    name: '',
    description: '',
    duration_minutes: 30,
    buffer_after_minutes: 0,
    price: '0.00',
    is_active: true,
    staff_profile_ids: [] as number[],
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.defaults({
        name: props.service?.name ?? '',
        description: props.service?.description ?? '',
        duration_minutes: props.service?.duration_minutes ?? 30,
        buffer_after_minutes: props.service?.buffer_after_minutes ?? 0,
        price: props.service?.price ?? '0.00',
        is_active: props.service?.is_active ?? true,
        // New services are offered by everyone who takes bookings.
        staff_profile_ids:
            props.service?.staff_profile_ids ??
            props.staff.map((member) => member.id),
    });
    form.reset();
});

const submit = () => {
    const route = props.service ? update(props.service.id) : store();

    form.submit(route, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
};

const toggleStaff = (id: number, checked: boolean) => {
    form.staff_profile_ids = checked
        ? [...form.staff_profile_ids, id]
        : form.staff_profile_ids.filter((value) => value !== id);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>
                        {{ service ? 'Edit service' : 'New service' }}
                    </DialogTitle>
                    <DialogDescription>
                        Customers see the name, duration and price on your
                        booking page.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="service-name">Name</Label>
                    <Input
                        id="service-name"
                        v-model="form.name"
                        required
                        maxlength="100"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="service-description">Description</Label>
                    <Textarea
                        id="service-description"
                        v-model="form.description"
                        rows="2"
                        maxlength="1000"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="service-duration">Duration (min)</Label>
                        <Input
                            id="service-duration"
                            v-model.number="form.duration_minutes"
                            type="number"
                            min="5"
                            max="480"
                            step="5"
                            required
                        />
                        <InputError :message="form.errors.duration_minutes" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="service-buffer">Buffer after (min)</Label>
                        <Input
                            id="service-buffer"
                            v-model.number="form.buffer_after_minutes"
                            type="number"
                            min="0"
                            max="120"
                            step="5"
                            required
                        />
                        <InputError
                            :message="form.errors.buffer_after_minutes"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="service-price">Price</Label>
                        <Input
                            id="service-price"
                            v-model="form.price"
                            inputmode="decimal"
                            required
                        />
                        <InputError :message="form.errors.price" />
                    </div>
                </div>

                <fieldset v-if="staff.length" class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">
                        Who performs it
                    </legend>
                    <label
                        v-for="member in staff"
                        :key="member.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            class="size-4 accent-primary"
                            :checked="
                                form.staff_profile_ids.includes(member.id)
                            "
                            @change="
                                toggleStaff(
                                    member.id,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: member.color }"
                        />
                        {{ member.name }}
                    </label>
                    <InputError :message="form.errors.staff_profile_ids" />
                </fieldset>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 accent-primary"
                    />
                    Show on the booking page
                </label>

                <DialogFooter>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-service-button"
                    >
                        <Spinner v-if="form.processing" />
                        Save
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
