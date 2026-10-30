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
        <Label
            :for="switchId"
            class="cursor-pointer text-xs font-semibold tracking-wide text-muted-foreground uppercase"
        >
            Проверен
        </Label>

        <Switch
            :id="switchId"
            :model-value="modelValue"
            aria-label="Проверен"
            @update:model-value="emit('update:modelValue', Boolean($event))"
        />
    </div>
</template>
