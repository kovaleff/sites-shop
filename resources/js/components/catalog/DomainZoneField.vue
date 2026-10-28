<script setup>
import { computed } from 'vue';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: Array, required: true },
    zones: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const allZonesActive = computed(() => props.modelValue.length === 0);

/**
 * @param {string[]} next
 * @returns {void}
 */
function updateZones(next) {
    emit('update:modelValue', Array.isArray(next) ? next : []);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            Домен / зона
        </span>

        <div v-if="loading" class="flex flex-wrap gap-2">
            <Skeleton v-for="index in 4" :key="index" class="h-8 w-16 rounded-md" />
        </div>

        <div v-else class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                size="sm"
                :variant="allZonesActive ? 'default' : 'outline'"
                :class="cn('h-8', allZonesActive && 'shadow-sm')"
                :aria-pressed="allZonesActive"
                @click="updateZones([])"
            >
                Все зоны
            </Button>

            <ToggleGroup
                type="multiple"
                aria-label="Доменные зоны"
                :roving-focus="false"
                variant="outline"
                size="sm"
                :model-value="modelValue"
                @update:model-value="updateZones"
            >
                <ToggleGroupItem
                    v-for="zone in zones"
                    :key="zone.value"
                    :value="zone.value"
                    class="h-8 px-3"
                >
                    {{ zone.label }}
                </ToggleGroupItem>
            </ToggleGroup>
        </div>
    </div>
</template>
