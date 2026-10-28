<script setup>
import { useId } from 'vue';

import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

defineProps({
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const switchId = useId();
</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            Проверен
        </span>

        <div class="flex min-h-9 items-center gap-3 rounded-md border border-input bg-secondary/40 px-3">
            <Switch
                :id="switchId"
                :model-value="modelValue"
                @update:model-value="emit('update:modelValue', Boolean($event))"
            />
            <Label :for="switchId" class="cursor-pointer text-sm text-foreground">
                Проверен
            </Label>
        </div>
    </div>
</template>
