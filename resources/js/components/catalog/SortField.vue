<script setup>
import { useId } from 'vue';
import { ChevronDown } from '@lucide/vue';

import { Label } from '@/components/ui/label';
import { DEFAULT_SORT, SORT_OPTIONS } from '@/lib/catalogConfig';

defineProps({
    modelValue: { type: String, default: DEFAULT_SORT },
});

const emit = defineEmits(['update:modelValue']);

const selectId = useId();
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label
            :for="selectId"
            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
        >
            Сортировка
        </Label>

        <div class="relative">
            <select
                :id="selectId"
                class="h-8 w-full appearance-none rounded-md border border-input bg-secondary/60 px-3 pr-8 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                :value="modelValue"
                aria-label="Сортировка каталога"
                @change="emit('update:modelValue', String($event.target.value))"
            >
                <option v-for="option in SORT_OPTIONS" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>

            <ChevronDown
                class="pointer-events-none absolute top-1/2 right-2 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
        </div>

        <div class="h-4" aria-hidden="true" />
    </div>
</template>
